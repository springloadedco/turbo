# Turbo

Springloaded's Laravel AI development toolkit.

## Overview

This is a Laravel package that provides AI guidelines, skills, and tools for Springloaded projects via Laravel Boost integration.

## Development

- PHP 8.4+, Laravel 11/12
- Uses Spatie Laravel Package Tools

### Commands

- `composer test` - Run tests (Pest)
- `composer analyse` - Static analysis (PHPStan)
- `composer format` - Code formatting (Pint)

### Artisan Commands

This package registers artisan commands in `src/Commands/`. In a consumer Laravel project, they are called with `php artisan <command>`. When developing this package, use `bin/turbo` which wraps Orchestra Testbench:

- `bin/turbo install` - Install/configure Turbo in a project
- `bin/turbo build` - Build + push a custom Docker sandbox image
- `bin/turbo claude` - Launch interactive Claude session in a sandbox
- `bin/turbo prompt {prompt}` - Send a one-shot prompt to Claude in a sandbox
- `bin/turbo exec {command}` - Execute arbitrary commands inside the sandbox
- `bin/turbo prepare` - Configure sandbox host access (/etc/hosts + policy)
- `bin/turbo ports` - List / publish / unpublish sandbox ports
- `bin/turbo start` / `bin/turbo stop` / `bin/turbo rm` - Sandbox lifecycle
- `bin/turbo doctor` - Diagnose sandbox environment health
- `bin/turbo skills` - Manage AI skills (not useful during package development — publishes skills to the Testbench workbench directory, not a real project)

### Package Structure

- `src/` - Package source code
- `.ai/skills/` - AI skills published to consumer projects
- `config/` - Publishable config
- `tests/` - Pest tests

### How Skills Work

The `.ai/skills/` directory contains Laravel development patterns that get published to projects installing Turbo. Each skill has a SKILL.md with usage triggers and examples.

### Commit Conventions

- Use conventional commits
- `feat` is reserved for changes that impact the **public API** of the package
- Internal tooling (`.agents/skills/`, config, CI, etc.) should use `chore`, not `feat`

### Testing

Uses Pest with Orchestra Testbench. Run `composer test`.

### sbx CLI Reference

**Authoritative docs** (always consult before speculating about sbx capabilities):
- CLI reference: https://docs.docker.com/reference/cli/sbx/
- Sandboxes manual: https://docs.docker.com/ai/sandboxes/
- Custom environments: https://docs.docker.com/ai/sandboxes/agents/custom-environments/
- Network policy: https://docs.docker.com/reference/cli/sbx/policy/
- Isolation & security: https://docs.docker.com/ai/sandboxes/security/

Install: `brew install docker/tap/sbx`

#### sbx Capability Matrix

**Supported on `sbx create` / `sbx run`:**
- `--template <image>` — custom container image (pulled from an OCI registry)
- `--name <name>` — custom sandbox name (default: `<agent>-<workdir>`)
- `--branch <name>` — Git worktree on a branch (`--branch auto` on `run` to auto-generate)
- `-m, --memory <size>` — memory limit (e.g. `8g`; default ~50% host memory, max 32 GiB)
- Workspace positional args — mount host paths at the **same absolute path** in the sandbox (append `:ro` for read-only; multiple paths supported)

**NOT supported on create/run** — handle differently:
- `--env` / `--env-file` → use `sbx secret set` for supported services, or write to `/etc/sandbox-persistent.sh` via `sbx exec` for custom env vars
- `--add-host` → modify `/etc/hosts` via `sbx exec` or bake into a custom template
- `--volume` / `--mount` → only workspace positional args; no arbitrary remapping
- `--publish` / `-p` → use `sbx ports <name> --publish <spec>` post-creation
- `--dns` / `--hostname` → DNS resolution is handled by the host proxy, not the sandbox
- `--cpus` / `--user` / `--entrypoint` → not supported

#### Secret Injection Model

sbx secrets are **not** env vars inside the VM — they're injected at the network layer as HTTP auth headers by the host proxy:

- **Supported services** (proxy-injected, raw value never enters VM): `anthropic`, `aws`, `github`, `google`, `groq`, `mistral`, `nebius`, `openai`, `xai`
- Set globally: `sbx secret set -g <service> -t <token>` (applies at sandbox creation time)
- Set per-sandbox: `sbx secret set <name> <service> -t <token>` (takes effect immediately)
- **Global secret changes require sandbox recreation** to take effect; per-sandbox updates are live.

For **custom env vars** (unsupported services), write to `/etc/sandbox-persistent.sh` inside the VM:
```bash
sbx exec -d <name> bash -c "echo 'export FOO=bar' >> /etc/sandbox-persistent.sh"
```
This stores the value inside the VM — less secure than proxy-injected secrets.

#### Host Access Patterns

From inside a sandbox, to reach services on the host machine:

1. Use `host.docker.internal` (resolves to host; proxy auto-translates to `localhost`)
2. Allowlist the port: `sbx policy allow network localhost:11434`
3. `curl http://host.docker.internal:11434/...` now works

For **custom hostnames** (e.g. Laravel Herd/Valet routing `myapp.test` → host): sbx has **no native `/etc/hosts` support**. Options: (a) modify via `sbx exec sudo tee -a /etc/hosts` at install time, (b) bake into a custom template, (c) use `host.docker.internal` + `Host:` header. Turbo uses option (a) via `turbo:prepare`.

#### Main Commands

**`sbx create [OPTIONS] AGENT WORKSPACE`**
- Create a sandbox with access to a host workspace for an agent
- Available agents: `claude`, `codex`, `copilot`, `docker-agent`, `gemini`, `kiro`, `opencode`, `shell`
- Workspace path is required and exposed inside sandbox at same path as host
- Options:
  - `--name string` - Custom sandbox name (default: `<agent>-<workdir>`)
  - `--template string` - Custom container image for sandbox
  - `--branch string` - Create a Git worktree on the given branch
  - `-m, --memory string` - Memory limit (e.g., `1024m`, `8g`)

**`sbx run <AGENT> [WORKSPACE] [-- AGENT_ARGS...] | SANDBOX [-- AGENT_ARGS...]`**
- Run an agent in a sandbox; creates sandbox if it doesn't exist
- Workspace defaults to current directory if omitted
- Pass agent arguments after `--` separator
- Examples:
  - `sbx run claude` - Create/run sandbox with claude in current dir
  - `sbx run existing-sandbox` - Run existing sandbox
  - `sbx run claude . -- -p "What version are you running?"` - Run with agent args
  - `sbx run --branch my-feature claude` - Run on an isolated git worktree branch
- Options: same as `create` command, plus `--branch`

**`sbx exec [OPTIONS] SANDBOX COMMAND [ARG...]`**
- Execute a command in an existing sandbox
- Options:
  - `-i, --interactive` - Keep STDIN open
  - `-t, --tty` - Allocate a pseudo-TTY

**`sbx ls`**
- List all sandboxes with status
- Options:
  - `-q, --quiet` - Only display sandbox names

**`sbx rm SANDBOX [SANDBOX...]`**
- Remove one or more sandboxes and all associated resources
- Options:
  - `--all` - Remove all sandboxes

**`sbx stop SANDBOX [SANDBOX...]`**
- Stop one or more sandboxes without removing them
- Sandboxes can be restarted later

**`sbx ports SANDBOX [OPTIONS]`**
- List, publish, or unpublish sandbox ports
- Services inside the sandbox must bind to `0.0.0.0` (not `127.0.0.1`) to be reachable
- Options:
  - `--publish [[HOST_IP:]HOST_PORT:]SANDBOX_PORT[/PROTOCOL]` - Publish a port
  - `--unpublish [[HOST_IP:]HOST_PORT:]SANDBOX_PORT[/PROTOCOL]` - Unpublish a port
- Default host IP: `127.0.0.1`. Default protocol: `tcp`.
- Examples:
  - `sbx ports claude-myapp --publish 8080:8000` - Publish sandbox's 8000 on host's 8080
  - `sbx ports claude-myapp --publish 127.0.0.1:5173:5173` - Bind to loopback
  - `sbx ports claude-myapp` - List published ports

**`sbx reset [OPTIONS]`**
- Reset all sandboxes and permanently delete all data
- Options:
  - `--preserve-secrets` - Keep stored secrets

**`sbx save SANDBOX TAG [OPTIONS]`**
- Save a snapshot of sandbox as a template
- Examples:
  - `sbx save my-sandbox myimage:v1.0` - Load into host Docker
  - `sbx save my-sandbox myimage:v1.0 --output /tmp/myimage.tar` - Save to file
- Options:
  - `-o, --output string` - Save to tar file instead of loading into Docker

**`sbx policy allow network <hosts>`**
- Allow network access to specific domains
- Examples:
  - `sbx policy allow network registry.npmjs.org`
  - `sbx policy allow network "*.example.com:443,example.com:443"`
  - `sbx policy allow network localhost:11434` - Allow access to host services

**`sbx policy ls`**
- Display active network access rules

**`sbx policy reset`**
- Restore default network policy

**`sbx secret set [OPTIONS] <service>`**
- Store credentials in OS keychain for injection into sandboxes
- Examples:
  - `sbx secret set -g anthropic` - Set Anthropic API key
  - `sbx secret set -g github -t "$(gh auth token)"` - Set GitHub token

**`sbx login`**
- Docker OAuth sign-in via browser

**`sbx version`**
- Show version information

### Docker Sandbox Patterns

#### Image Registry Requirement
- sbx uses a separate Docker daemon that does NOT share the local image store
- Templates must be pulled from an OCI registry (Docker Hub, GHCR, etc.)
- Default image: `docker.io/springloadedco/turbo:latest` — published via CI
- `turbo:build` is only needed for custom images extending the published one

#### Symfony Process: TTY vs PTY vs pcntl_exec
- `pcntl_exec()` — replaces the PHP process with the child. Use for **fully-interactive TUIs** (Claude Code, bash) because Symfony's `setTty(true)` does NOT properly allocate a pty for them (causes exit 137 SIGKILL after welcome screen renders). See `DockerSandbox::runInteractive()`.
- `setPty(true)` — creates pseudo-terminal for output capture. Use for **non-interactive** command execution that needs streaming output (e.g. `turbo:prompt`).
- `setTty(true)` — connects real terminal stdin/stdout/stderr directly. Works for simple commands but NOT for Ink/Textual-based TUIs.
- Check `isTtySupported()` / `isPtySupported()` before calling.

#### Don't run `sbx exec` before `sbx run` in the same PHP process
- Running `sbx exec` via Symfony Process immediately before `sbx run` via `pcntl_exec` causes the claude agent to be SIGKILL'd (exit 137) after rendering its welcome screen.
- Root cause unclear — likely sbx's session lifecycle interacts badly with the rapid exec-then-run sequence from PHP.
- Fix: do any `sbx exec` prep steps at sandbox creation time (`turbo:install`) or via a separate artisan command (`turbo:prepare`), not before `turbo:claude`.

#### sbx Commands
- `sbx run <name> -- <args>` — args after `--` go to `claude` CLI
- `-p "prompt"` sends a **prompt** to Claude (natural language)
- `plugin marketplace add ...` is a **CLI subcommand**, not a prompt — pass directly without `-p`
- Don't use try-then-fallback pattern for sandbox existence — command failures inside an existing sandbox are indistinguishable from "sandbox not found." Use `sandboxExists()` check instead.

#### Idempotent Plugin Install
- `claude plugin marketplace add` fails with exit 1 if marketplace already installed
- Treat "already installed" as success, not failure — check error output for "already installed" string

#### Docker Build UX
- `--progress=quiet` suppresses all output — use `ProgressIndicator` spinner with `start()`/`advance()` pattern instead of `run()` with output callback
