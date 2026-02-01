<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class DockerBuildCommand extends Command
{
    protected $signature = 'turbo:build';

    protected $description = 'Build the Turbo Docker sandbox image';

    public function handle(DockerSandbox $sandbox): int
    {
        $this->info('Building Turbo sandbox image...');
        $this->newLine();
        $this->line('Dockerfile: '.$sandbox->dockerfile);
        $this->newLine();

        $process = $sandbox->buildProcess();

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $this->newLine();

        if ($process->isSuccessful()) {
            $this->info('Sandbox image built successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed to build sandbox image.');

        return self::FAILURE;
    }
}
