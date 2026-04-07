<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class StopCommand extends Command
{
    protected $signature = 'turbo:stop';

    protected $description = 'Stop the sandbox (preserving state)';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist.");

            return self::FAILURE;
        }

        $this->info("Stopping sandbox '{$sandbox->sandboxName()}'...");

        $process = $sandbox->stopProcess();
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
