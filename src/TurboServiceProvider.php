<?php

namespace Springloaded\Turbo;

use Illuminate\Support\Facades\File;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Springloaded\Turbo\Commands\ClaudeCommand;
use Springloaded\Turbo\Commands\DockerBuildCommand;
use Springloaded\Turbo\Commands\PromptCommand;
use Springloaded\Turbo\Commands\PublishSkillsCommand;
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
                PublishSkillsCommand::class,
            ]);
    }

    public function bootingPackage(): void
    {
        $configPath = config_path('turbo.php');

        if (! File::exists($configPath) && ! $this->app->runningUnitTests()) {
            File::copy(__DIR__.'/../config/turbo.php', $configPath);
        }
    }
}
