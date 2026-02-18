![Turbo](turbo.png)

## What is Turbo?

Turbo is Springloaded's opinionated toolkit for AI-assisted Laravel development. It combines open source [Superpowers](https://github.com/obra/superpowers) — brainstorming, plan creation, and plan execution — with Springloaded's own standards, skills, and tooling to create a consistent, high-quality environment for building applications.

### Superpowers Workflow

Turbo includes the [Superpowers](https://github.com/obra/superpowers) plugin, which provides a structured development workflow through slash commands:

```
/brainstorming ──> /writing-plans ──> /executing-plans ──> Review
                        ^                                    │
                        └────────── needs changes ───────────┘
```

| Command | What it does |
|---------|-------------|
| `/brainstorming` | Explore the idea — ask questions, consider approaches, produce a design |
| `/writing-plans` | Turn the design into a step-by-step implementation plan |
| `/executing-plans` | Execute the plan with review checkpoints between steps |

Other superpowers activate automatically when relevant — test-driven development before writing code, systematic debugging when something breaks, verification before claiming work is done.

### Skills

**Skills** encode how Springloaded builds Laravel apps — controllers, actions, testing, validation, Inertia, GitHub workflows, and more. They work with any agent that supports skills (Claude, Cursor, Codex, GitHub Copilot) via [`npx skills`](https://skills.sh), so the whole team builds the same way regardless of which agent they use.

### Docker Sandbox

**Docker Sandbox** lets you run Claude in an isolated environment with your project workspace mounted, so agents can work freely without touching your local machine. Build the sandbox image once, then launch interactive sessions or fire off one-shot prompts from the command line.

### Feedback Loops

**Feedback Loops** wire your project's verification commands (tests, linting, static analysis) directly into skill templates, so agents check their own work as they go.

### Prerequisites

- PHP 8.4+
- Laravel 11 or 12
- Node.js / npm (required for `npx skills`)
- Docker (required for sandbox commands)

## Installation

```bash
composer require springloadedco/turbo --dev
```

### From a Local Clone

If you have Turbo cloned locally and want to symlink it instead, point Composer at your local clone:

```bash
composer config repositories.turbo path /path/to/turbo
```

You'll need to disable symlinking if you plan on using Laravel Boost. If you install without the symlink, you'll need to re-run composer require any time you want to pull changes.

```bash
composer config repositories.turbo.options.symlink false
```

Then require the package:

```bash
composer require springloadedco/turbo:@dev --dev
```

Composer will automatically symlink the local directory, so changes are reflected immediately without re-installing.

## Getting Started

Run the install command to configure skills, set up a GitHub token, and build the Docker sandbox:

```bash
php artisan turbo:install
```

Then launch an interactive Claude session in the sandbox:

```bash
php artisan turbo:claude
```

On the first run, you'll be prompted to authenticate with Claude. This only needs to be done once per sandbox.

To re-publish Turbo's skills after a package update:

```bash
php artisan turbo:skills
```

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
    'image'      => env('TURBO_DOCKER_IMAGE', 'turbo'),
    'dockerfile' => env('TURBO_DOCKER_DOCKERFILE'),
    'workspace'  => env('TURBO_DOCKER_WORKSPACE', base_path()),
],
```

| Key | Description | Default |
|-----|-------------|---------|
| `image` | Docker image tag used for build and run | `turbo` |
| `dockerfile` | Path to a custom Dockerfile (falls back to the one shipped with Turbo) | `null` |
| `workspace` | Local directory mounted into the sandbox | `base_path()` |

## Commands

| Command | Description |
|---------|-------------|
| `turbo:install` | Set up Turbo for your project (see [Getting Started](#getting-started)) |
| `turbo:skills` | Re-publish Turbo skills after a package update |
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

## Development

When working on Turbo itself, use `bin/turbo` to run commands via Orchestra Testbench:

```bash
bin/turbo install    # turbo:install
bin/turbo build      # turbo:build
bin/turbo claude     # turbo:claude
bin/turbo prompt "…" # turbo:prompt
```

Optionally, install [direnv](https://direnv.net) to drop the `bin/` prefix and just use `turbo <command>`:

```bash
brew install direnv
```

Add the hook to your shell (`~/.zshrc`):

```bash
eval "$(direnv hook zsh)"
```

Then allow the project's `.envrc`:

```bash
direnv allow
```

After that, `turbo claude`, `turbo build`, etc. work directly whenever you're in the project directory.

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
