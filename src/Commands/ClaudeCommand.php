<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class ClaudeCommand extends Command
{
    protected $signature = 'turbo:claude';

    protected $description = 'Start an interactive Claude session in the Docker sandbox';

    public function handle(DockerSandbox $sandbox): int
    {
        $sandbox->runInteractive();

        // pcntl_exec replaces the PHP process, so this is never reached.
        return self::SUCCESS;
    }
}
