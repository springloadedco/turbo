<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class PrepareCommand extends Command
{
    protected $signature = 'turbo:prepare';

    protected $description = 'Configure sandbox host access (/etc/hosts entries and proxy bypasses)';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $this->info('Preparing sandbox...');

        $sandbox->prepareSandbox();

        $port = (int) config('turbo.oauth.callback_port', 33418);

        $this->info('Sandbox prepared.');
        $this->info("OAuth callback relay listening on localhost:{$port} — use `php artisan turbo:mcp:add` to register OAuth MCP servers.");

        return self::SUCCESS;
    }
}
