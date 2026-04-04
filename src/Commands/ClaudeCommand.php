<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class ClaudeCommand extends Command
{
    protected $signature = 'turbo:claude';

    protected $description = 'Start an interactive Claude session in the Docker sandbox';

    public function handle(DockerSandbox $sandbox): never
    {
        // runInteractive() replaces the PHP process via pcntl_exec.
        $sandbox->runInteractive();
    }
}
