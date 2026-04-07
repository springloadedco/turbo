<?php

namespace Springloaded\Turbo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Springloaded\Turbo\Commands\ClaudeCommand;
use Springloaded\Turbo\Commands\DoctorCommand;
use Springloaded\Turbo\Commands\ExecCommand;
use Springloaded\Turbo\Commands\InstallCommand;
use Springloaded\Turbo\Commands\PortsCommand;
use Springloaded\Turbo\Commands\PrepareCommand;
use Springloaded\Turbo\Commands\PromptCommand;
use Springloaded\Turbo\Commands\RemoveCommand;
use Springloaded\Turbo\Commands\SkillsCommand;
use Springloaded\Turbo\Commands\StartCommand;
use Springloaded\Turbo\Commands\StopCommand;

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
                InstallCommand::class,
                ClaudeCommand::class,
                PromptCommand::class,
                ExecCommand::class,
                PrepareCommand::class,
                PortsCommand::class,
                StartCommand::class,
                StopCommand::class,
                RemoveCommand::class,
                DoctorCommand::class,
                SkillsCommand::class,
            ]);
    }
}
