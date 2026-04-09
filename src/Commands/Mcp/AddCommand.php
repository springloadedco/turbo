<?php

namespace Springloaded\Turbo\Commands\Mcp;

use Illuminate\Console\Command;
use Springloaded\Turbo\Services\DockerSandbox;

class AddCommand extends Command
{
    protected $signature = 'turbo:mcp:add
        {name : MCP server name}
        {url : MCP server URL}
        {--scope=user : MCP server scope (user, project, local)}
        {--transport=http : Transport type (http or sse)}';

    protected $description = 'Register an OAuth-capable MCP server inside the sandbox with a pinned callback port';

    public function handle(DockerSandbox $sandbox): int
    {
        if (! $sandbox->sandboxExists()) {
            $this->error("Sandbox '{$sandbox->sandboxName()}' does not exist. Run turbo:install first.");

            return self::FAILURE;
        }

        $port = (int) config('turbo.oauth.callback_port', 33418);
        $name = $this->argument('name');
        $url = $this->argument('url');
        $scope = $this->option('scope');
        $transport = $this->option('transport');

        $process = $sandbox->execProcess([
            'claude', 'mcp', 'add',
            '--transport', $transport,
            '--callback-port', (string) $port,
            '--scope', $scope,
            $name, $url,
        ]);

        $process->run(function (string $type, string $buffer): void {
            echo $buffer;
        });

        if (! $process->isSuccessful()) {
            $this->error("Failed to register MCP server '{$name}'.");

            return self::FAILURE;
        }

        $this->info("MCP server '{$name}' registered with OAuth callback port {$port}.");
        $this->info('Run `php artisan turbo:claude` and use /mcp to complete OAuth in your browser.');

        return self::SUCCESS;
    }
}
