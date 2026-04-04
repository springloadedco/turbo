<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class DoctorCommand extends Command
{
    protected $signature = 'turbo:doctor';

    protected $description = 'Run a health check on the Turbo sandbox environment';

    protected bool $allOk = true;

    public function handle(DockerSandbox $sandbox): int
    {
        $this->info('Turbo sandbox health check');
        $this->newLine();

        $this->checkSbxInstalled();
        $this->checkSandboxExists($sandbox);

        if ($sandbox->sandboxExists()) {
            $this->checkHostsConfigured($sandbox);
            $this->checkPublishedPorts($sandbox);
        }

        $this->newLine();

        if ($this->allOk) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        $this->warn('Some checks failed. See messages above.');

        return self::FAILURE;
    }

    protected function checkSbxInstalled(): void
    {
        $path = trim((string) shell_exec('command -v sbx'));

        if ($path === '') {
            $this->reportFailure('sbx CLI', 'not found on PATH. Install with: brew install docker/tap/sbx');

            return;
        }

        $version = trim((string) shell_exec('sbx version 2>/dev/null | head -n 1'));
        $this->pass('sbx CLI', $version !== '' ? $version : $path);
    }

    protected function checkSandboxExists(DockerSandbox $sandbox): void
    {
        $name = $sandbox->sandboxName();

        if (! $sandbox->sandboxExists()) {
            $this->reportFailure("Sandbox '{$name}'", 'does not exist. Run turbo:install to create it.');

            return;
        }

        $this->pass("Sandbox '{$name}'", 'exists');
    }

    protected function checkHostsConfigured(DockerSandbox $sandbox): void
    {
        $hosts = $sandbox->resolveHosts();

        if (empty($hosts)) {
            $this->pass('Host access', 'no hosts configured (no APP_URL)');

            return;
        }

        foreach ($hosts as $host) {
            $hostname = str_contains($host, ':') ? explode(':', $host)[0] : $host;

            $process = $sandbox->execProcess(['grep', '-qwF', $hostname, '/etc/hosts']);
            $process->run();

            if ($process->isSuccessful()) {
                $this->pass("Host '{$hostname}'", 'configured in /etc/hosts');
            } else {
                $this->reportFailure("Host '{$hostname}'", 'NOT in /etc/hosts. Run turbo:prepare.');
            }
        }
    }

    protected function checkPublishedPorts(DockerSandbox $sandbox): void
    {
        $process = $sandbox->portsProcess();
        $process->run();

        $output = trim($process->getOutput());

        if ($output === '' || str_contains($output, 'No ports')) {
            $this->pass('Published ports', 'none');

            return;
        }

        $this->pass('Published ports', 'listed below');
        $this->line($output);
    }

    protected function pass(string $label, string $detail): void
    {
        $this->line("  <fg=green>✓</> {$label}: {$detail}");
    }

    protected function reportFailure(string $label, string $detail): void
    {
        $this->allOk = false;
        $this->line("  <fg=red>✗</> {$label}: {$detail}");
    }
}
