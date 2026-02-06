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
