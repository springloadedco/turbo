<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Commands\Concerns\DisplaysCommands;
use Springloaded\Turbo\Services\DockerSandbox;

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

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $this->newLine();

        if ($process->isSuccessful()) {
            $this->info('Sandbox image built successfully.');

            $sandbox->prompt(
                'plugin marketplace add obra/superpowers-marketplace'
            )->run(fn($type, $buffer) => $this->output->write($buffer));

            $sandbox->prompt(
                'plugin install superpowers@superpowers-marketplace'
            )->run(fn($type, $buffer) => $this->output->write($buffer));

            return self::SUCCESS;
        }

        $this->error('Failed to build sandbox image.');

        return self::FAILURE;
    }
}
