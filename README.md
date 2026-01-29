# Springloadeds secret sauce for Laravel AI development.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/springloadedco/turbo.svg?style=flat-square)](https://packagist.org/packages/springloadedco/turbo)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/springloadedco/turbo/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/springloadedco/turbo/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/springloadedco/turbo/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/springloadedco/turbo/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/springloadedco/turbo.svg?style=flat-square)](https://packagist.org/packages/springloadedco/turbo)

## Installation

You can install the package via composer:

```bash
composer require springloadedco/turbo
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="turbo-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="turbo-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="turbo-views"
```

## Usage

```php
$turbo = new Springloaded\Turbo();
echo $turbo->echoPhrase('Hello, Springloaded!');
```

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
