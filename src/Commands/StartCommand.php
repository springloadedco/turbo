<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class StartCommand extends Command
{
    protected $signature = 'turbo:start';

    protected $description = 'Start the sandbox (without attaching an agent session)';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $this->info("Starting sandbox '{$sandbox->sandboxName()}'...");

        // sbx has no dedicated start command; exec auto-starts stopped sandboxes.
        $process = $sandbox->execProcess(['true']);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
