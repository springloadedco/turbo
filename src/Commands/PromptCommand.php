<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Console\Helper\ProgressIndicator;

class PromptCommand extends Command
{
    protected $signature = 'turbo:prompt {prompt : The prompt to send to Claude}';

    protected $description = 'Run Claude with a prompt in the sandbox';

    public function handle(DockerSandbox $sandbox): int
    {
        $prompt = $this->argument('prompt');

        $process = $sandbox->promptProcess($prompt);

        $this->info(Str::remove("'", $process->getCommandLine()));

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
            $exitCode = $process->getExitCode();
            $this->error($process->getErrorOutput());

            if ($exitCode === 137) {
                $this->warn('The Claude agent was killed (exit 137). You may need to authenticate first.');
                $this->line('Run <comment>turbo:claude</comment> and use <comment>/login</comment> to authenticate.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
