<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Springloaded\Turbo\Services\DockerSandbox;

class ClaudeCommand extends Command
{
    protected $signature = 'turbo:claude';

    protected $description = 'Start an interactive Claude session in the Docker sandbox';

    public function handle(DockerSandbox $sandbox): int
    {
        $process = match ($sandbox->sandboxExists()) {
            true => $sandbox->runSandbox(),
            false => $sandbox->createSandbox(),
        };

        $this->info(Str::remove("'", $process->getCommandLine()));

        $process->run();

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
