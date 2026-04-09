# Sandbox OAuth Relay Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make OAuth-based MCP servers (Figma, etc.) authenticate end-to-end from inside a Turbo sandbox without manual keychain extraction.

**Architecture:** Pin Claude Code's MCP OAuth callback port via config, publish that port host→sandbox with `sbx ports --publish`, and run a `socat` relay inside the sandbox to bridge `0.0.0.0:PORT` → `127.0.0.1:PORT` (Claude Code's listener binds to localhost only). Wrap `claude mcp add --callback-port` in a new artisan command so users never have to think about the port.

**Tech Stack:** PHP 8.4, Laravel 12, Spatie Laravel Package Tools, Symfony Process, Pest, Mockery, sbx CLI, socat.

---

## File Structure

| Path | Action | Responsibility |
|---|---|---|
| `config/turbo.php` | modify | Add `oauth.callback_port` knob |
| `src/Services/DockerSandbox.php` | modify | Add `publishOauthPortProcess`, `startOauthRelayProcess`, `setupOauthRelay`; call from `prepareSandbox` |
| `src/Commands/PrepareCommand.php` | modify | Print OAuth relay status after prepare |
| `src/Commands/Mcp/AddCommand.php` | create | New `turbo:mcp:add` artisan command |
| `src/TurboServiceProvider.php` | modify | Register `Mcp\AddCommand` |
| `docker/turbo-oauth-relay.sh` | create | Shell wrapper around `socat` |
| `Dockerfile` | modify | Install `socat`, COPY relay script, chmod +x |
| `tests/Unit/DockerSandboxTest.php` | modify | Tests for new methods |
| `tests/Unit/PrepareCommandTest.php` | modify | Verify new info output |
| `tests/Unit/Mcp/AddCommandTest.php` | create | Tests for new command |
| `CLAUDE.md` | modify | Replace Figma keychain workaround section |
| `README.md` | modify | Brief MCP OAuth note |

---

## Task 1: Add OAuth callback port config

**Files:**
- Modify: `config/turbo.php`

- [ ] **Step 1: Add the `oauth` config block**

Edit `config/turbo.php`. After the closing `],` of the `'docker'` array (currently ending around line 64), and before the closing `];` of the return array, add:

```php
    'oauth' => [
        /*
        |--------------------------------------------------------------------------
        | Callback Port
        |--------------------------------------------------------------------------
        |
        | The port Claude Code uses for MCP OAuth callbacks. This port is
        | published from the sandbox to the host's localhost so the OAuth
        | provider's redirect (http://localhost:PORT/callback) reaches the
        | listener inside the sandbox. A socat relay inside the sandbox
        | bridges 0.0.0.0:PORT to 127.0.0.1:PORT because Claude Code binds
        | the OAuth listener to localhost only.
        |
        | Change this only if 33418 conflicts with something on your host.
        |
        */
        'callback_port' => env('TURBO_OAUTH_CALLBACK_PORT', 33418),
    ],
```

- [ ] **Step 2: Verify config loads**

Run: `cd /Users/sagalbot/Sites/turbo && bin/turbo about 2>/dev/null || vendor/bin/testbench tinker --execute='echo config("turbo.oauth.callback_port");'`

Expected: prints `33418` (or check just by reading the file back — no test yet, that's Task 2).

- [ ] **Step 3: Commit**

```bash
git add config/turbo.php
git commit -m "feat: add turbo.oauth.callback_port config"
```

---

## Task 2: Add `publishOauthPortProcess` method

**Files:**
- Modify: `src/Services/DockerSandbox.php`
- Test: `tests/Unit/DockerSandboxTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/DockerSandboxTest.php` (after the existing `unpublishPortProcess` test around line 107):

```php
it('creates a publish OAuth port process with same host and sandbox port', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->publishOauthPortProcess(33418);

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('sbx')
        ->toContain('ports')
        ->toContain('claude-cpbc')
        ->toContain('--publish')
        ->toContain('33418:33418');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="creates a publish OAuth port process"`
Expected: FAIL with "Method publishOauthPortProcess does not exist".

- [ ] **Step 3: Implement the method**

Add to `src/Services/DockerSandbox.php` immediately after the `publishPortProcess` method (around line 219, before `unpublishPortProcess`):

```php
    /**
     * Create a process to publish the MCP OAuth callback port.
     *
     * Publishes the same port number on host and sandbox so the OAuth
     * provider's redirect URI (http://localhost:PORT/callback) routes
     * from the host browser into the sandbox.
     */
    public function publishOauthPortProcess(int $port): Process
    {
        return $this->publishPortProcess("{$port}:{$port}");
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="creates a publish OAuth port process"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Services/DockerSandbox.php tests/Unit/DockerSandboxTest.php
git commit -m "feat: add publishOauthPortProcess method"
```

---

## Task 3: Add `startOauthRelayProcess` method

**Files:**
- Modify: `src/Services/DockerSandbox.php`
- Test: `tests/Unit/DockerSandboxTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/DockerSandboxTest.php` after the test from Task 2:

```php
it('creates a start OAuth relay process that runs socat in background via sbx exec', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->startOauthRelayProcess(33418);

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('sbx')
        ->toContain('exec')
        ->toContain('claude-cpbc')
        ->toContain('bash')
        ->toContain('-lc')
        ->toContain('turbo-oauth-relay')
        ->toContain('TURBO_OAUTH_PORT=33418')
        ->toContain('pgrep');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="creates a start OAuth relay process"`
Expected: FAIL with "Method startOauthRelayProcess does not exist".

- [ ] **Step 3: Implement the method**

Add to `src/Services/DockerSandbox.php` immediately after `publishOauthPortProcess`:

```php
    /**
     * Create a process to start the OAuth relay daemon inside the sandbox.
     *
     * Runs socat (via /usr/local/bin/turbo-oauth-relay from the image) as a
     * detached background process. The relay listens on 0.0.0.0:PORT and
     * forwards to 127.0.0.1:PORT, bridging the gap between sbx port
     * publishing (which targets eth0) and Claude Code's localhost-only
     * OAuth listener.
     *
     * Idempotent: pgrep skips the launch if a relay is already running.
     */
    public function startOauthRelayProcess(int $port): Process
    {
        $script = sprintf(
            "pgrep -f 'turbo-oauth-relay' >/dev/null 2>&1 || (TURBO_OAUTH_PORT=%d nohup /usr/local/bin/turbo-oauth-relay >/tmp/turbo-oauth-relay.log 2>&1 &)",
            $port
        );

        return $this->execProcess(['bash', '-lc', $script]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="creates a start OAuth relay process"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Services/DockerSandbox.php tests/Unit/DockerSandboxTest.php
git commit -m "feat: add startOauthRelayProcess method"
```

---

## Task 4: Add `setupOauthRelay` orchestration

**Files:**
- Modify: `src/Services/DockerSandbox.php`
- Test: `tests/Unit/DockerSandboxTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/DockerSandboxTest.php`:

```php
it('setupOauthRelay publishes the configured port and starts the relay', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $publishProcess = Mockery::mock(Process::class);
    $publishProcess->shouldReceive('run')->once();

    $relayProcess = Mockery::mock(Process::class);
    $relayProcess->shouldReceive('run')->once();

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('publishOauthPortProcess')->with(33418)->once()->andReturn($publishProcess);
    $sandbox->shouldReceive('startOauthRelayProcess')->with(33418)->once()->andReturn($relayProcess);

    $sandbox->setupOauthRelay();
});

it('setupOauthRelay reads the port from config', function () {
    config()->set('turbo.oauth.callback_port', 9999);

    $publishProcess = Mockery::mock(Process::class);
    $publishProcess->shouldReceive('run')->once();

    $relayProcess = Mockery::mock(Process::class);
    $relayProcess->shouldReceive('run')->once();

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('publishOauthPortProcess')->with(9999)->once()->andReturn($publishProcess);
    $sandbox->shouldReceive('startOauthRelayProcess')->with(9999)->once()->andReturn($relayProcess);

    $sandbox->setupOauthRelay();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="setupOauthRelay"`
Expected: FAIL with "Method setupOauthRelay does not exist".

- [ ] **Step 3: Implement the method**

Add to `src/Services/DockerSandbox.php` immediately after `startOauthRelayProcess`:

```php
    /**
     * Set up the MCP OAuth callback path for this sandbox.
     *
     * Publishes the callback port host→sandbox and starts the relay
     * daemon inside the sandbox. Both steps are idempotent and safe to
     * re-run; failures of the publish step (already published) are
     * intentionally ignored.
     */
    public function setupOauthRelay(): void
    {
        $port = (int) config('turbo.oauth.callback_port', 33418);

        $this->publishOauthPortProcess($port)->run();
        $this->startOauthRelayProcess($port)->run();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="setupOauthRelay"`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
git add src/Services/DockerSandbox.php tests/Unit/DockerSandboxTest.php
git commit -m "feat: add setupOauthRelay orchestration"
```

---

## Task 5: Wire `setupOauthRelay` into `prepareSandbox`

**Files:**
- Modify: `src/Services/DockerSandbox.php`
- Test: `tests/Unit/DockerSandboxTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/DockerSandboxTest.php`:

```php
it('prepareSandbox calls setupOauthRelay after host setup', function () {
    $prepareProcess = Mockery::mock(Process::class);
    $prepareProcess->shouldReceive('run')->once();

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('resolveHosts')->andReturn([]);
    $sandbox->shouldReceive('prepareSandboxProcess')->andReturn($prepareProcess);
    $sandbox->shouldReceive('setupOauthRelay')->once();

    $sandbox->prepareSandbox();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="prepareSandbox calls setupOauthRelay"`
Expected: FAIL — `setupOauthRelay` was never called.

- [ ] **Step 3: Wire it into prepareSandbox**

In `src/Services/DockerSandbox.php`, modify the `prepareSandbox` method (currently lines 127–139) to add the relay setup as the final step:

```php
    public function prepareSandbox(): void
    {
        // Configure proxy bypasses from the host side
        $hosts = $this->resolveHosts();
        foreach ($hosts as $host) {
            $this->proxyBypassProcess($host)->run();
        }

        // Run setup script inside the sandbox
        $this->prepareSandboxProcess()->run(function (string $type, string $buffer): void {
            echo $buffer;
        });

        // Set up the MCP OAuth callback relay (publish port + start socat)
        $this->setupOauthRelay();
    }
```

- [ ] **Step 4: Run the new test plus the full DockerSandbox suite**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter=DockerSandbox`
Expected: PASS — all DockerSandbox tests including the new one.

- [ ] **Step 5: Commit**

```bash
git add src/Services/DockerSandbox.php tests/Unit/DockerSandboxTest.php
git commit -m "feat: wire OAuth relay setup into prepareSandbox"
```

---

## Task 6: Update `PrepareCommand` to advertise the OAuth port

**Files:**
- Modify: `src/Commands/PrepareCommand.php`
- Test: `tests/Unit/PrepareCommandTest.php`

- [ ] **Step 1: Update the existing PrepareCommand test**

Replace the second test in `tests/Unit/PrepareCommandTest.php` (`'calls prepareSandbox when sandbox exists'`, lines 17–27) with:

```php
it('calls prepareSandbox and prints the OAuth callback port when sandbox exists', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('prepareSandbox')->once();

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:prepare')
        ->expectsOutputToContain('OAuth callback relay listening on localhost:33418')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter="OAuth callback relay listening"`
Expected: FAIL — output does not contain the expected string.

- [ ] **Step 3: Update PrepareCommand**

Replace the `handle` method in `src/Commands/PrepareCommand.php` with:

```php
    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $this->info('Preparing sandbox...');

        $sandbox->prepareSandbox();

        $port = (int) config('turbo.oauth.callback_port', 33418);

        $this->info('Sandbox prepared.');
        $this->info("OAuth callback relay listening on localhost:{$port} — use `php artisan turbo:mcp:add` to register OAuth MCP servers.");

        return self::SUCCESS;
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter=PrepareCommand`
Expected: PASS — both PrepareCommand tests.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/PrepareCommand.php tests/Unit/PrepareCommandTest.php
git commit -m "feat: advertise OAuth relay port in turbo:prepare output"
```

---

## Task 7: Create `turbo:mcp:add` command

**Files:**
- Create: `src/Commands/Mcp/AddCommand.php`
- Create: `tests/Unit/Mcp/AddCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Mcp/AddCommandTest.php`:

```php
<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])->assertFailed();
});

it('runs claude mcp add inside the sandbox with the configured callback port', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(true);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')
        ->withArgs(function (array $command) {
            return $command === [
                'claude', 'mcp', 'add',
                '--transport', 'http',
                '--callback-port', '33418',
                '--scope', 'user',
                'figma', 'https://mcp.figma.com/mcp',
            ];
        })
        ->once()
        ->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])
        ->expectsOutputToContain("MCP server 'figma' registered with OAuth callback port 33418")
        ->assertSuccessful();
});

it('honours --scope and --transport options', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(true);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')
        ->withArgs(function (array $command) {
            return in_array('--scope', $command, true)
                && in_array('project', $command, true)
                && in_array('--transport', $command, true)
                && in_array('sse', $command, true);
        })
        ->once()
        ->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
        '--scope' => 'project',
        '--transport' => 'sse',
    ])->assertSuccessful();
});

it('reports failure when claude mcp add exits non-zero', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(false);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])->assertFailed();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter=Mcp`
Expected: FAIL — `turbo:mcp:add` command does not exist.

- [ ] **Step 3: Create the command**

Create `src/Commands/Mcp/AddCommand.php`:

```php
<?php

namespace Springloaded\Turbo\Commands\Mcp;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class AddCommand extends Command
{
    protected $signature = 'turbo:mcp:add
        {name : MCP server name}
        {url : MCP server URL}
        {--scope=user : MCP server scope (user, project, local)}
        {--transport=http : Transport type (http or sse)}';

    protected $description = 'Register an OAuth-capable MCP server inside the sandbox with a pinned callback port';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $port = (int) config('turbo.oauth.callback_port', 33418);
        $name = $this->argument('name');
        $url = $this->argument('url');
        $scope = $this->option('scope');
        $transport = $this->option('transport');

        $process = $sandbox->execProcess([
            'claude', 'mcp', 'add',
            '--transport', $transport,
            '--callback-port', (string) $port,
            '--scope', $scope,
            $name, $url,
        ]);

        $process->run(function (string $type, string $buffer): void {
            echo $buffer;
        });

        if (! $process->isSuccessful()) {
            $this->error("Failed to register MCP server '{$name}'.");

            return self::FAILURE;
        }

        $this->info("MCP server '{$name}' registered with OAuth callback port {$port}.");
        $this->info('Run `php artisan turbo:claude` and use /mcp to complete OAuth in your browser.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register the command in the service provider**

In `src/TurboServiceProvider.php`, add an import and the command to the `hasCommands` array:

```php
use Springloaded\Turbo\Commands\Mcp\AddCommand as McpAddCommand;
```

Then in `hasCommands([...])` add `McpAddCommand::class,` (place it after `SkillsCommand::class,`).

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/sagalbot/Sites/turbo && composer test -- --filter=Mcp`
Expected: PASS — all four AddCommand tests.

- [ ] **Step 6: Commit**

```bash
git add src/Commands/Mcp/AddCommand.php src/TurboServiceProvider.php tests/Unit/Mcp/AddCommandTest.php
git commit -m "feat: add turbo:mcp:add command for OAuth-pinned MCP registration"
```

---

## Task 8: Add the relay shell script and bake into the image

**Files:**
- Create: `docker/turbo-oauth-relay.sh`
- Modify: `Dockerfile`

- [ ] **Step 1: Create the relay shell script**

Create `docker/turbo-oauth-relay.sh`:

```bash
#!/usr/bin/env bash
# Turbo OAuth callback relay.
#
# Bridges 0.0.0.0:PORT to 127.0.0.1:PORT inside the sandbox so that
# Claude Code's localhost-bound MCP OAuth listener becomes reachable via
# `sbx ports --publish` (which routes host traffic to eth0).
#
# Port is set via TURBO_OAUTH_PORT (defaults to 33418).
set -euo pipefail

PORT="${TURBO_OAUTH_PORT:-33418}"

exec socat \
    "TCP-LISTEN:${PORT},bind=0.0.0.0,reuseaddr,fork" \
    "TCP:127.0.0.1:${PORT}"
```

- [ ] **Step 2: Update the Dockerfile**

Edit `Dockerfile`. In the apt install line at line 5–9, add `socat` to the package list:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
  php-cli php-mbstring php-xml php-curl php-zip php-intl php-bcmath php-sqlite3 php-mysql php-gd \
  php-redis php-pgsql php-imagick php-memcached \
  socat \
  unzip ca-certificates \
  && rm -rf /var/lib/apt/lists/*
```

Then immediately after the existing `COPY docker/setup-sandbox.sh ...` block (lines 39–41), add:

```dockerfile
# OAuth callback relay (socat wrapper for MCP OAuth flows)
COPY docker/turbo-oauth-relay.sh /usr/local/bin/turbo-oauth-relay
RUN chmod +x /usr/local/bin/turbo-oauth-relay
```

- [ ] **Step 3: Build the image locally to verify**

Run: `cd /Users/sagalbot/Sites/turbo && docker build -t springloadedco/turbo:oauth-test .`
Expected: build succeeds, no errors.

- [ ] **Step 4: Smoke-test the relay script in the image**

Run: `docker run --rm springloadedco/turbo:oauth-test bash -c 'which socat && which turbo-oauth-relay && cat /usr/local/bin/turbo-oauth-relay | head -5'`
Expected: prints `/usr/bin/socat`, `/usr/local/bin/turbo-oauth-relay`, and the script header.

- [ ] **Step 5: Commit**

```bash
git add docker/turbo-oauth-relay.sh Dockerfile
git commit -m "feat: bake socat-based OAuth relay into sandbox image"
```

---

## Task 9: Update CLAUDE.md — replace Figma keychain workaround

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Find the existing Figma section**

Run: `grep -n -i "figma\|find-generic-password\|credentials.json" /Users/sagalbot/Sites/turbo/CLAUDE.md` to locate the section to replace. (No section currently exists in `CLAUDE.md` per our exploration — the workaround lives in the user's verbal context, not in the repo. So this task **adds** a new short section.)

- [ ] **Step 2: Add an MCP OAuth section to CLAUDE.md**

In `/Users/sagalbot/Sites/turbo/CLAUDE.md`, after the existing `### sbx CLI Reference` block, add a new section:

```markdown
### MCP OAuth Callbacks

OAuth-based MCP servers (Figma, etc.) authenticate end-to-end inside the sandbox via the OAuth relay set up by `turbo:prepare`:

1. The callback port is pinned via `config('turbo.oauth.callback_port')` (default 33418).
2. `turbo:prepare` publishes that port host→sandbox with `sbx ports --publish` and starts a `socat` daemon inside the sandbox that bridges `0.0.0.0:PORT → 127.0.0.1:PORT` (Claude Code binds the OAuth listener to localhost only).
3. Register OAuth MCP servers with `php artisan turbo:mcp:add <name> <url>` — this wraps `claude mcp add --callback-port` with the pinned port automatically.

When the OAuth provider redirects the host browser to `http://localhost:33418/callback`, the callback flows host → sbx port publish → socat relay → Claude Code listener inside the sandbox. No keychain extraction, no manual `.credentials.json` edits.

If `33418` conflicts with another service on your host, set `TURBO_OAUTH_CALLBACK_PORT` in your project's `.env` and re-run `turbo:prepare`.
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: document MCP OAuth callback relay flow"
```

---

## Task 10: Update README.md with brief MCP OAuth note

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Locate the right section**

Run: `grep -n "## " /Users/sagalbot/Sites/turbo/README.md` to find a sensible insertion point — typically after the "Commands" or "Sandbox" section.

- [ ] **Step 2: Add a short MCP OAuth note**

Add the following section to `README.md` (placement: after the commands listing, before any "Configuration" or "Contributing" section):

```markdown
### OAuth MCP Servers

Some MCP servers (Figma, Linear, etc.) use OAuth for authentication. Turbo pins Claude Code's OAuth callback port and runs a relay inside the sandbox so the host browser's OAuth redirect reaches the sandboxed Claude Code session.

Register an OAuth MCP server:

```bash
php artisan turbo:mcp:add figma https://mcp.figma.com/mcp
```

Then run `php artisan turbo:claude` and use `/mcp` to complete OAuth in your browser. See `CLAUDE.md` for details.
```

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: README note for OAuth MCP servers"
```

---

## Task 11: Full test suite + static analysis

**Files:** none

- [ ] **Step 1: Run the full test suite**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: PASS — all tests, no regressions.

- [ ] **Step 2: Run static analysis**

Run: `cd /Users/sagalbot/Sites/turbo && composer analyse`
Expected: PASS — no new PHPStan errors.

- [ ] **Step 3: Run formatter**

Run: `cd /Users/sagalbot/Sites/turbo && composer format`
Expected: PASS — files are correctly formatted (or auto-fixes applied).

- [ ] **Step 4: Commit any formatter changes**

```bash
git status
# If pint touched anything:
git add -u
git commit -m "style: pint"
```

---

## Verification (manual, post-implementation)

After all tasks complete and the new image is published (CI builds & pushes `springloadedco/turbo:latest` from `main`):

1. **Fresh sandbox** in a test Laravel project:
   - `php artisan turbo:install` → answer prompts → sandbox created → `prepareSandbox` runs.
2. **Verify port published**: on host, `sbx ports <sandbox-name>` shows `33418:33418/tcp`.
3. **Verify relay running**: `php artisan turbo:exec -- pgrep -fa turbo-oauth-relay` returns a PID.
4. **Verify network path**: on host, `curl -v http://localhost:33418/` connects (will get a connection-refused from the upstream socket if Claude Code isn't mid-OAuth — that's fine, it confirms the relay is accepting and forwarding).
5. **End-to-end OAuth**:
   - `php artisan turbo:mcp:add figma https://mcp.figma.com/mcp`
   - `php artisan turbo:claude` → `/mcp` → authenticate Figma
   - Host browser opens, completes OAuth, sandboxed Claude Code receives the callback and stores tokens. **No keychain dance.**
6. **Token persistence**: kill the sandbox session, restart `turbo:claude`, Figma MCP still authenticated.
