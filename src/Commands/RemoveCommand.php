<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

use function Laravel\Prompts\confirm;

class RemoveCommand extends Command
{
    protected $signature = 'turbo:rm {--force : Skip confirmation}';

    protected $description = 'Remove the sandbox and all its state';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->info("Sandbox '{$sandbox->sandboxName()}' does not exist.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            $confirmed = confirm(
                label: "Remove sandbox '{$sandbox->sandboxName()}'? This destroys all state inside the sandbox.",
                default: false,
            );

            if (! $confirmed) {
                return self::SUCCESS;
            }
        }

        $this->info("Removing sandbox '{$sandbox->sandboxName()}'...");

        $process = $sandbox->removeProcess();
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
