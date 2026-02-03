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
