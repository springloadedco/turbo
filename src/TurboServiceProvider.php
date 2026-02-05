<?php

namespace Springloaded\Turbo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Springloaded\Turbo\Commands\ClaudeCommand;
use Springloaded\Turbo\Commands\DockerBuildCommand;
use Springloaded\Turbo\Commands\InstallCommand;
use Springloaded\Turbo\Commands\PromptCommand;
use Springloaded\Turbo\Commands\SkillsCommand;
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
            ->hasCommands([
                TurboCommand::class,
                DockerBuildCommand::class,
                ClaudeCommand::class,
                PromptCommand::class,
                InstallCommand::class,
                SkillsCommand::class,
            ]);
    }

}
