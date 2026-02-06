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

### Package Structure

- `src/` - Package source code
- `.ai/skills/` - AI skills published to consumer projects
- `config/` - Publishable config
- `tests/` - Pest tests

### How Skills Work

The `.ai/skills/` directory contains Laravel development patterns that get published to projects installing Turbo. Each skill has a SKILL.md with usage triggers and examples.

### Testing

Uses Pest with Orchestra Testbench. Run `composer test`.

### Docker Sandbox Commands Reference

#### Main Commands

**`docker sandbox create [OPTIONS] AGENT WORKSPACE`**
- Create a sandbox with access to a host workspace for an agent
- Available agents: `claude`, `cagent`, `codex`, `copilot`, `gemini`, `kiro`
- Workspace path is required and exposed inside sandbox at same path as host
- Options:
  - `--name string` - Custom sandbox name (default: `<agent>-<workdir>`)
  - `-t, --template string` - Custom container image for sandbox
  - `--load-local-template` - Load locally built template image
  - `-q, --quiet` - Suppress verbose output
  - `-D, --debug` - Enable debug logging

**`docker sandbox run SANDBOX [-- AGENT_ARGS...] | AGENT WORKSPACE [-- AGENT_ARGS...]`**
- Run an agent in a sandbox; creates sandbox if it doesn't exist
- Pass agent arguments after `--` separator
- Examples:
  - `docker sandbox run claude .` - Create/run sandbox with claude in current dir
  - `docker sandbox run existing-sandbox` - Run existing sandbox
  - `docker sandbox run claude . -- -p "What version are you running?"` - Run with agent args
- Options: same as `create` command

**`docker sandbox exec [OPTIONS] SANDBOX COMMAND [ARG...]`**
- Execute a command in an existing sandbox
- Options:
  - `-i, --interactive` - Keep STDIN open
  - `-t, --tty` - Allocate a pseudo-TTY
  - `-d, --detach` - Run in background
  - `-e, --env stringArray` - Set environment variables
  - `--env-file stringArray` - Read environment variables from file
  - `-u, --user string` - Username or UID
  - `-w, --workdir string` - Working directory inside container
  - `--privileged` - Give extended privileges

**`docker sandbox ls [OPTIONS]`**
- List all VMs and their sandboxes
- Aliases: `list`
- Options:
  - `-q, --quiet` - Only display VM names
  - `--json` - Output in JSON format
  - `--no-trunc` - Don't truncate output

**`docker sandbox rm SANDBOX [SANDBOX...]`**
- Remove one or more sandboxes and all associated resources
- Aliases: `remove`

**`docker sandbox stop SANDBOX [SANDBOX...]`**
- Stop one or more sandboxes without removing them
- Sandboxes can be restarted later

**`docker sandbox reset [OPTIONS]`**
- Reset all VM sandboxes and permanently delete all VM data
- ⚠️ WARNING: Destructive operation - stops all VMs, deletes state, clears registries
- Options:
  - `-f, --force` - Skip confirmation prompt

**`docker sandbox save SANDBOX TAG [OPTIONS]`**
- Save a snapshot of sandbox as a template
- Examples:
  - `docker sandbox save my-sandbox myimage:v1.0` - Load into host Docker
  - `docker sandbox save my-sandbox myimage:v1.0 --output /tmp/myimage.tar` - Save to file
- Options:
  - `-o, --output string` - Save to tar file instead of loading into Docker

**`docker sandbox network log [OPTIONS]`**
- Show network logs
- Options:
  - `--json` - Output in JSON format
  - `--limit int` - Maximum number of log entries to show
  - `-q, --quiet` - Only display log entries

**`docker sandbox network proxy <sandbox> [OPTIONS]`**
- Manage proxy configuration for a sandbox
- Options:
  - `--policy allow|deny` - Set default policy
  - `--allow-host string` - Permit access to domain/IP (can be specified multiple times)
  - `--allow-cidr string` - Remove IP range from block/bypass lists (can be specified multiple times)
  - `--block-host string` - Block access to domain/IP (can be specified multiple times)
  - `--block-cidr string` - Block access to IP range in CIDR notation (can be specified multiple times)
  - `--bypass-host string` - Bypass proxy for domain/IP (can be specified multiple times)
  - `--bypass-cidr string` - Bypass proxy for IP range in CIDR notation (can be specified multiple times)

**`docker sandbox version`**
- Show sandbox version information

### Docker Sandbox Patterns

#### Symfony Process: TTY vs PTY
- `setTty(true)` — connects real terminal stdin/stdout/stderr directly. Use for **interactive** sessions (e.g. `turbo:claude`)
- `setPty(true)` — creates pseudo-terminal for output capture. Use for **non-interactive** command execution (e.g. `turbo:prompt`, `runCommand`)
- Check `isTtySupported()` before `setTty()`, `isPtySupported()` before `setPty()`

#### Docker Sandbox Commands
- `docker sandbox run <name> -- <args>` — args after `--` go to `claude` CLI
- `-p "prompt"` sends a **prompt** to Claude (natural language)
- `plugin marketplace add ...` is a **CLI subcommand**, not a prompt — pass directly without `-p`
- Don't use try-then-fallback pattern for sandbox existence — command failures inside an existing sandbox are indistinguishable from "sandbox not found." Use `sandboxExists()` check instead.

#### Idempotent Plugin Install
- `claude plugin marketplace add` fails with exit 1 if marketplace already installed
- Treat "already installed" as success, not failure — check error output for "already installed" string

#### Docker Build UX
- `--progress=quiet` suppresses all output — use `ProgressIndicator` spinner with `start()`/`advance()` pattern instead of `run()` with output callback
