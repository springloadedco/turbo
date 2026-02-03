![Turbo](turbo.png)

Springloaded's secret sauce for Laravel AI development.

## What is Turbo?

Turbo supercharges AI-assisted Laravel development by providing:

- **AI Skills & Guidelines** - Curated patterns for Laravel development (controllers, actions, testing, validation, Inertia) and GitHub workflow automation
- **Multi-Agent Support** - Publish skills to Claude, Cursor, Codex, and other AI agents via [`npx skills`](https://skills.sh)
- **Feedback Loops** - Configurable verification commands injected into skill templates at publish time
- **Docker Sandbox** - Build and run Claude in a sandboxed Docker environment with your project workspace mounted
- **Artisan Commands** - Publish skills, build sandbox images, and run Claude sessions from the command line

### Prerequisites

- PHP 8.4+
- Laravel 11 or 12
- Node.js / npm (required for `npx skills`)
- Docker (required for sandbox commands)

## Installation

```bash
composer require springloadedco/turbo --dev
```

## Publishing Skills

Run the publish command to install skills into your project:

```bash
php artisan turbo:publish
```

This command:

1. Runs `npx skills add` interactively, pre-selecting all available skills
2. Prompts you to choose which agents to install to (Claude, Cursor, Codex, etc.)
3. Processes skill templates, injecting your project's configured feedback loops
4. Optionally configures a GitHub token for `gh` CLI access (stored in `.claude/settings.local.json`)

## Skills

Turbo publishes the following skills to your project:

| Skill | Description |
|-------|-------------|
| `laravel-actions` | Business logic encapsulation patterns |
| `laravel-controllers` | Invokable controller patterns with Inertia |
| `laravel-testing` | Pest/PHPUnit testing best practices |
| `laravel-validation` | Form Request validation patterns |
| `laravel-inertia` | TypeScript page component patterns |
| `github-issue` | Create atomic GitHub issues for agent execution |
| `github-labels` | Apply consistent labels to GitHub issues |
| `github-milestone` | Create well-structured GitHub milestones |
| `github-pr-comment` | Add progress comments to PRs during agent execution |

## Configuration

Publish the config file to customize Turbo's behavior:

```bash
php artisan vendor:publish --tag=turbo-config
```

This creates `config/turbo.php` where you can configure feedback loops — the verification commands injected into skill templates at publish time:

```php
'feedback_loops' => [
    'composer lint',
    'composer test',
    'composer analyse',
    'npm run lint',
    'npm run types',
    'npm run build',
    'npm run test',
],
```

Remove or add commands to match your project's toolchain. These are rendered into skill templates via the `{{ $feedback_loops }}` and `{{ $feedback_loops_checklist }}` placeholders.

The config also includes Docker sandbox settings:

```php
'docker' => [
    'image'      => env('TURBO_DOCKER_IMAGE', 'turbo-sandbox'),
    'dockerfile' => env('TURBO_DOCKER_DOCKERFILE'),
    'workspace'  => env('TURBO_DOCKER_WORKSPACE', base_path()),
],
```

| Key | Description | Default |
|-----|-------------|---------|
| `image` | Docker image tag used for build and run | `turbo-sandbox` |
| `dockerfile` | Path to a custom Dockerfile (falls back to the one shipped with Turbo) | `null` |
| `workspace` | Local directory mounted into the sandbox | `base_path()` |

## Commands

| Command | Description |
|---------|-------------|
| `turbo:publish` | Publish AI skills to your project (see [Publishing Skills](#publishing-skills)) |
| `turbo:build` | Build the Docker sandbox image |
| `turbo:claude` | Start an interactive Claude session in the Docker sandbox |
| `turbo:prompt {prompt}` | Run Claude with a one-off prompt in the Docker sandbox |

### Docker Sandbox

Turbo ships a Dockerfile based on `docker/sandbox-templates:claude-code` with PHP 8.4, common extensions, and Composer pre-installed.

**Build the image:**

```bash
php artisan turbo:build
```

**Start an interactive Claude session:**

```bash
php artisan turbo:claude
```

This opens an interactive Claude session inside the sandbox with your project workspace mounted.

**Run a one-off prompt:**

```bash
php artisan turbo:prompt "Write tests for the UserController"
```

Sends the prompt to Claude inside the sandbox and streams the output back to your terminal.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jeff Sagal](https://github.com/sagalbot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
