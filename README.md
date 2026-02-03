![Turbo](turbo.png)

Springloaded's secret sauce for Laravel AI development.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/springloadedco/turbo.svg?style=flat-square)](https://packagist.org/packages/springloadedco/turbo)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/springloadedco/turbo/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/springloadedco/turbo/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/springloadedco/turbo/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/springloadedco/turbo/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/springloadedco/turbo.svg?style=flat-square)](https://packagist.org/packages/springloadedco/turbo)

## What is Turbo?

Turbo supercharges AI-assisted Laravel development by providing:

- **AI Skills & Guidelines** - Curated patterns for Laravel development (controllers, actions, testing, validation, Inertia) via Laravel Boost
- **Docker Sandbox Support** - Ready-to-use Dockerfile for `docker sandbox run`
- **Artisan Commands** - Shortcuts for common AI development workflows (coming soon)

## Installation

```bash
composer require springloadedco/turbo --dev
```

## Features

### AI Skills

Turbo publishes development skills to your project:

| Skill | Description |
|-------|-------------|
| `laravel-actions` | Business logic encapsulation patterns |
| `laravel-controllers` | Invokable controller patterns with Inertia |
| `laravel-testing` | Pest/PHPUnit testing best practices |
| `laravel-validation` | Form Request validation patterns |
| `laravel-inertia` | TypeScript page component patterns |

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
