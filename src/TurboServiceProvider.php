<?php

namespace Springloaded\Turbo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Springloaded\Turbo\Commands\TurboCommand;

class TurboServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('turbo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_turbo_table')
            ->hasCommand(TurboCommand::class);
    }
}
