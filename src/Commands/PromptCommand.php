<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Console\Helper\ProgressIndicator;

class PromptCommand extends Command
{
    protected $signature = 'turbo:prompt {prompt : The prompt to send to Claude}';

    protected $description = 'Run Claude with a prompt in the Docker sandbox';

    public function handle(DockerSandbox $sandbox): int
    {
        $prompt = $this->argument('prompt');

        $process = $sandbox->promptProcess($prompt);
        $process->start();

        $progress = new ProgressIndicator($this->output);
        $progress->start('Running...');

        while ($process->isRunning()) {
            $progress->advance();
            usleep(100000);
        }

        $progress->finish('Done');
        $this->newLine();

        $this->line($process->getOutput());

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
