# Migrate Docker Sandbox to sbx CLI - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all `docker sandbox` CLI calls with the new standalone `sbx` CLI, and update the proxy bypass mechanism to use `sbx policy allow network`.

**Architecture:** The `DockerSandbox` service wraps CLI commands via Symfony Process. Every command array that starts with `['docker', 'sandbox', ...]` becomes `['sbx', ...]`. The network proxy bypass (`docker sandbox network proxy <name> --bypass-host`) becomes `sbx policy allow network <host>`. The `create` command drops the `--name` flag and the agent/workspace positional args change. The `run` command no longer needs a separate `create` first since `sbx run` auto-creates. The image build still uses `docker build` but `create` now uses `--template` instead of `-t`.

**Tech Stack:** PHP 8.4, Pest, Symfony Process

---

## Command Mapping Reference

| Old Command | New Command |
|---|---|
| `docker sandbox ls -q` | `sbx ls -q` |
| `docker sandbox create -t <img> --name <n> claude <workspace>` | `sbx create --template <img> --name <n> claude <workspace>` |
| `docker sandbox rm <name>` | `sbx rm <name>` |
| `docker sandbox exec <name> <cmd...>` | `sbx exec <name> <cmd...>` |
| `docker sandbox run <name> [-- args]` | `sbx run <name> [-- args]` |
| `docker sandbox network proxy <name> --bypass-host <host>` | `sbx policy allow network <host>` |
| `docker build ...` | `docker build ...` (unchanged) |

---

### Task 1: Update `DockerSandbox` Service — Core Commands

**Files:**
- Modify: `src/Services/DockerSandbox.php`

- [ ] **Step 1: Update `sandboxExists()` — replace `docker sandbox ls` with `sbx ls`**

In `src/Services/DockerSandbox.php`, change the process command in `sandboxExists()`:

```php
public function sandboxExists(): bool
{
    $process = new Process(['sbx', 'ls', '-q']);
    $process->run();
```

- [ ] **Step 2: Update `createProcess()` — replace `docker sandbox create` with `sbx create`**

Change the command array in `createProcess()`. Note: `-t` becomes `--template`:

```php
public function createProcess(): Process
{
    return $this->process([
        'sbx', 'create',
        '--template', $this->image,
        '--name', $this->sandboxName(),
        'claude',
        $this->workspace,
    ]);
}
```

- [ ] **Step 3: Update `removeProcess()` — replace `docker sandbox rm` with `sbx rm`**

```php
public function removeProcess(): Process
{
    return $this->process([
        'sbx', 'rm',
        $this->sandboxName(),
    ]);
}
```

- [ ] **Step 4: Update `execProcess()` — replace `docker sandbox exec` with `sbx exec`**

```php
public function execProcess(array $command): Process
{
    return $this->process(array_merge([
        'sbx', 'exec',
        $this->sandboxName(),
    ], $command));
}
```

- [ ] **Step 5: Update `interactiveProcess()` — replace `docker sandbox run` with `sbx run`**

```php
$command = [
    'sbx', 'run',
    $this->sandboxName(),
];
```

- [ ] **Step 6: Update `promptProcess()` — replace `docker sandbox run` with `sbx run`**

```php
return $this->ptyProcess([
    'sbx', 'run',
    $this->sandboxName(),
    '--',
    '-p', $prompt,
]);
```

- [ ] **Step 7: Update `runInSandbox()` — replace `docker sandbox run` with `sbx run`**

```php
$command = array_merge([
    'sbx', 'run',
    $this->sandboxName(),
    '--',
], $claudeArgs);
```

---

### Task 2: Update `DockerSandbox` Service — Network Proxy

**Files:**
- Modify: `src/Services/DockerSandbox.php`

The old `docker sandbox network proxy <name> --bypass-host <host>` is replaced by `sbx policy allow network <host>`. The new command is global (not per-sandbox), so the sandbox name is no longer needed. The port syntax also changes — the old API appended `:80`, but `sbx policy` uses the host directly.

- [ ] **Step 1: Update `proxyBypassProcess()` to use `sbx policy allow network`**

```php
public function proxyBypassProcess(string $host): Process
{
    if (! str_contains($host, ':')) {
        $host = $host.':80';
    }

    return $this->process([
        'sbx', 'policy', 'allow', 'network',
        $host,
    ]);
}
```

---

### Task 3: Update Unit Tests

**Files:**
- Modify: `tests/Unit/DockerSandboxTest.php`

Every test that asserts on the process command line needs to be updated. Replace `->toContain('docker')` and `->toContain('sandbox')` assertions with `->toContain('sbx')`. The `create` test also needs `-t` changed to `--template`. The proxy test replaces `network`, `proxy`, and sandbox name assertions with `policy`, `allow`, `network`.

- [ ] **Step 1: Update `createProcess` test**

Replace the assertion block in the "creates a create process with correct command" test:

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('create')
    ->toContain('--template')
    ->toContain('turbo')
    ->toContain('--name')
    ->toContain('claude-cpbc')
    ->toContain('claude')
    ->toContain('/Users/dev/Sites/cpbc');
```

- [ ] **Step 2: Update `removeProcess` test**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('rm')
    ->toContain('claude-cpbc');
```

- [ ] **Step 3: Update `buildProcess` test**

The build process still uses `docker build`, so this test should remain unchanged. Verify it still passes.

- [ ] **Step 4: Update `execProcess` test**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('exec')
    ->toContain('claude-cpbc')
    ->toContain('bash')
    ->toContain('echo hello');
```

- [ ] **Step 5: Update `interactiveProcess` test**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('run')
    ->toContain('claude-cpbc')
    ->not->toContain('create');
```

- [ ] **Step 6: Update `promptProcess` test**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('run')
    ->toContain('claude-cpbc')
    ->toContain('--')
    ->toContain('-p')
    ->toContain('Hello Claude');
```

- [ ] **Step 7: Update `proxyBypassProcess` test (port 80)**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('policy')
    ->toContain('allow')
    ->toContain('network')
    ->toContain('app.test:80');
```

- [ ] **Step 8: Update `prepareSandboxProcess` test**

```php
expect($commandLine)
    ->toContain('sbx')
    ->toContain('exec')
    ->toContain('setup-sandbox')
    ->toContain('/Users/dev/Sites/cpbc')
    ->toContain('app.test:192.168.65.254');
```

- [ ] **Step 9: Update `prepareSandboxProcess` without hosts test**

```php
expect($commandLine)
    ->toContain('setup-sandbox')
    ->toContain('/Users/dev/Sites/cpbc')
    ->not->toContain(':192');
```

- [ ] **Step 10: Run all tests**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass.

- [ ] **Step 11: Commit**

```bash
git add src/Services/DockerSandbox.php tests/Unit/DockerSandboxTest.php
git commit -m "refactor: migrate docker sandbox CLI to sbx"
```

---

### Task 4: Update Documentation — `config/turbo.php` Comment

**Files:**
- Modify: `config/turbo.php`

- [ ] **Step 1: Update comment referencing `docker sandbox run`**

Change line 29's comment from:

```php
| passed to `docker sandbox run`.
```

to:

```php
| passed to `sbx create --template`.
```

---

### Task 5: Update Documentation — `CLAUDE.md`

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update Docker Sandbox Commands Reference section**

Replace the entire "Docker Sandbox Commands Reference" section. Update all `docker sandbox` references to `sbx`. Key changes:

- `docker sandbox create [OPTIONS] AGENT WORKSPACE` -> `sbx create [OPTIONS] AGENT WORKSPACE`
- `docker sandbox run SANDBOX [-- AGENT_ARGS...]` -> `sbx run SANDBOX [-- AGENT_ARGS...]`
- `docker sandbox exec [OPTIONS] SANDBOX COMMAND` -> `sbx exec [OPTIONS] SANDBOX COMMAND`
- `docker sandbox ls` -> `sbx ls`
- `docker sandbox rm` -> `sbx rm`
- `docker sandbox stop` -> `sbx stop`
- `docker sandbox save` -> `sbx save`
- `docker sandbox reset` -> `sbx reset`
- `docker sandbox version` -> `sbx version`
- `docker sandbox network proxy` -> `sbx policy allow network`

- [ ] **Step 2: Update Docker Sandbox Patterns section**

Update the patterns section to reference `sbx` instead of `docker sandbox`:
- `docker sandbox run <name> -- <args>` -> `sbx run <name> -- <args>`
- `docker sandbox exec` -> `sbx exec`
- References to "sandbox existence" checks

- [ ] **Step 3: Update `setup-sandbox.sh` doc comment**

In `docker/setup-sandbox.sh`, update line 4:

```bash
# Run via `sbx exec` before each Claude session.
```

---

### Task 6: Update Documentation — `README.md`

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update prerequisites**

Change line 43 from:

```
- Docker Desktop 4.61+ (required for sandbox commands)
```

to:

```
- sbx CLI (required for sandbox commands — `brew install docker/tap/sbx`)
```

- [ ] **Step 2: Update Docker Sandbox description**

In the "Docker Sandbox" section (line 29-31), update to reference `sbx` instead of `docker sandbox`.

---

### Task 7: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass.

- [ ] **Step 2: Run static analysis**

Run: `cd /Users/sagalbot/Sites/turbo && composer analyse`
Expected: No errors.

- [ ] **Step 3: Run formatter**

Run: `cd /Users/sagalbot/Sites/turbo && composer format`

- [ ] **Step 4: Commit documentation changes**

```bash
git add config/turbo.php CLAUDE.md README.md docker/setup-sandbox.sh
git commit -m "docs: update references from docker sandbox to sbx CLI"
```
