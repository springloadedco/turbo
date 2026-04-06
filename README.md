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
- sbx CLI (required for sandbox commands -- `brew install docker/tap/sbx`)

## Installation

Turbo is currently pre-release. Until a stable version is tagged, require it with `@dev`:

```bash
composer require springloadedco/turbo:@dev --dev
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

Turbo ships skills organized into groups. During `turbo:install` you pick which groups to install; individual skills within each group can be customized.

**Laravel patterns** — opinionated Laravel development conventions:

| Skill | Description |
|-------|-------------|
| `laravel-controllers` | Invokable controller patterns with Inertia |
| `laravel-actions` | Business logic encapsulation patterns |
| `laravel-validation` | Form Request validation patterns |
| `laravel-testing` | Pest/PHPUnit testing best practices |
| `laravel-inertia` | TypeScript page component patterns |

**Project utilities** — installed by default:

| Skill | Description |
|-------|-------------|
| `feedback-loops` | Enforces project verification commands before claiming work done, committing, or opening a PR |
| `agent-captures` | Standardizes agent-browser screenshot/PDF/video output locations |

**GitHub workflow** (opt-in):

| Skill | Description |
|-------|-------------|
| `github-issue` | Atomic issue creation with verifiable acceptance criteria |
| `github-labels` | Consistent label taxonomy (type/priority) |
| `github-milestone` | Well-structured milestones grouping related issues |

**Third-party integrations** (opt-in):

| Skill | Description |
|-------|-------------|
| `agent-browser` | Browser automation via [vercel-labs/agent-browser](https://agent-browser.dev/) |

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

Remove or add commands to match your project's toolchain. The `feedback-loops` skill uses these commands to enforce that agents verify their work before claiming tasks complete. They're rendered into skill templates via the `{{ $feedback_loops }}` and `{{ $feedback_loops_checklist }}` placeholders at install time.

The config also includes Docker sandbox settings:

```php
'docker' => [
    'image'     => env('TURBO_DOCKER_IMAGE', 'docker.io/springloadedco/turbo:latest'),
    'workspace' => env('TURBO_DOCKER_WORKSPACE', base_path()),
],
```

| Key | Description | Default |
|-----|-------------|---------|
| `image` | Fully-qualified OCI registry image for the sandbox template | `docker.io/springloadedco/turbo:latest` |
| `workspace` | Local directory mounted into the sandbox | `base_path()` |

## Commands

| Command | Description |
|---------|-------------|
| `turbo:install` | Set up Turbo for your project (see [Getting Started](#getting-started)) |
| `turbo:skills` | Re-publish Turbo skills after a package update |
| `turbo:claude` | Start an interactive Claude session in the sandbox |
| `turbo:prompt {prompt}` | Run Claude with a one-off prompt in the sandbox |
| `turbo:exec {command}` | Execute a command inside the sandbox |
| `turbo:prepare` | Configure sandbox host access (/etc/hosts + policy) |
| `turbo:ports` | List, publish, or unpublish sandbox ports |
| `turbo:start` | Start the sandbox (without attaching) |
| `turbo:stop` | Stop the sandbox (preserving state) |
| `turbo:rm` | Remove the sandbox and all its state |
| `turbo:doctor` | Run a health check on the sandbox environment |

### Docker Sandbox

Turbo publishes a pre-built sandbox image to Docker Hub as [`springloadedco/turbo`](https://hub.docker.com/r/springloadedco/turbo), based on `docker/sandbox-templates:claude-code` with PHP 8.4, common extensions, Composer, Node.js 22, and Chromium pre-installed.

Most users don't need to build anything — `turbo:install` uses the published image by default and sbx pulls it from Docker Hub.

**Extending the image:**

If your project needs additional tools, create a Dockerfile in your project root:

```dockerfile
FROM springloadedco/turbo:latest
USER root
RUN apt-get update && apt-get install -y redis-tools
USER agent
```

Set your own registry image in `.env`:

```
TURBO_DOCKER_IMAGE=docker.io/my-org/my-sandbox:latest
```

Then build and push with Docker:

```bash
docker build --push -t docker.io/my-org/my-sandbox:latest .
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

After that, `turbo claude`, `turbo prompt "…"`, etc. work directly whenever you're in the project directory.

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
