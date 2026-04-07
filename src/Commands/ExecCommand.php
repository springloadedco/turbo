<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class ExecCommand extends Command
{
    protected $signature = 'turbo:exec
        {cmd : The command to execute in the sandbox}
        {--tty : Allocate a pseudo-TTY for interactive commands (e.g. bash)}';

    protected $description = 'Execute a command inside the sandbox';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        /** @var string $cmd */
        $cmd = $this->argument('cmd');
        $args = ['bash', '-c', $cmd];

        if ($this->option('tty')) {
            $sandbox->execInteractive($args);
        }

        $process = $sandbox->execProcess($args);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
