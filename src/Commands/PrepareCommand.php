<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class PrepareCommand extends Command
{
    protected $signature = 'turbo:prepare';

    protected $description = 'Configure sandbox host access (/etc/hosts entries and proxy bypasses)';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $this->info('Preparing sandbox...');

        $sandbox->prepareSandbox();

        $this->info('Sandbox prepared.');

        return self::SUCCESS;
    }
}
