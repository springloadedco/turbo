<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Commands\Concerns\DisplaysCommands;
use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Console\Helper\ProgressIndicator;

class DockerBuildCommand extends Command
{
    use DisplaysCommands;

    protected $signature = 'turbo:build';

    protected $description = 'Build the Turbo Docker sandbox image';

    public function handle(DockerSandbox $sandbox): int
    {
        $this->info('Building Turbo sandbox image...');
        $this->line('Dockerfile: '.$sandbox->dockerfile);

        $process = $sandbox->buildProcess();

        $this->displayCommand($process);

        $process->start();

        $progress = new ProgressIndicator($this->output);
        $progress->start('Building...');

        while ($process->isRunning()) {
            $progress->advance();
            usleep(100000);
        }

        $progress->finish($process->isSuccessful() ? 'Done' : 'Failed');
        $this->newLine();

        if ($process->isSuccessful()) {
            $this->info('Sandbox image built successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed to build sandbox image.');
        $this->line($process->getErrorOutput());

        return self::FAILURE;
    }
}
