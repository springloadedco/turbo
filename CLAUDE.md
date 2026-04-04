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
- `bin/turbo build` - Build the Docker sandbox template
- `bin/turbo claude` - Launch interactive Claude session in a sandbox
- `bin/turbo prompt {prompt}` - Send a one-shot prompt to Claude in a sandbox
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

### sbx CLI Commands Reference

The `sbx` CLI (formerly `docker sandbox`) is a standalone tool for managing sandboxes.
Install: `brew install docker/tap/sbx`

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
- Default image: `docker.io/springloadedco/turbo:php8.4` — published via CI
- `turbo:build` is only needed for custom images extending the published one

#### Symfony Process: TTY vs PTY
- `setTty(true)` — connects real terminal stdin/stdout/stderr directly. Use for **interactive** sessions (e.g. `turbo:claude`)
- `setPty(true)` — creates pseudo-terminal for output capture. Use for **non-interactive** command execution (e.g. `turbo:prompt`, `runCommand`)
- Check `isTtySupported()` before `setTty()`, `isPtySupported()` before `setPty()`

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
