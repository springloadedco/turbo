<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class PortsCommand extends Command
{
    protected $signature = 'turbo:ports
        {--publish= : Publish a port spec (e.g. 8080:8000 or 127.0.0.1:5173:5173)}
        {--unpublish= : Unpublish a port spec}';

    protected $description = 'List, publish, or unpublish sandbox ports';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $publish = $this->option('publish');
        $unpublish = $this->option('unpublish');

        if ($publish && $unpublish) {
            $this->error('Cannot use --publish and --unpublish together.');

            return self::FAILURE;
        }

        $process = match (true) {
            (bool) $publish => $sandbox->publishPortProcess($publish),
            (bool) $unpublish => $sandbox->unpublishPortProcess($unpublish),
            default => $sandbox->portsProcess(),
        };

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
