<?php

namespace Springloaded\Turbo\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DockerSandbox
{
    public string $image;

    public string $dockerfile;

    public string $workspace;

    public function __construct()
    {
        $this->image = config('turbo.docker.image');
        $this->dockerfile = config('turbo.docker.dockerfile')
            ?? $this->getPackageDockerfilePath();
        $this->workspace = config('turbo.docker.workspace');
    }

    /**
     * Get the sandbox name used to identify this sandbox instance.
     *
     * Derived from the workspace path so each project gets its own sandbox.
     */
    public function sandboxName(): string
    {
        return 'claude-'.Str::slug(basename($this->workspace));
    }

    /**
     * Resolve hostnames the sandbox should be able to reach on the host.
     *
     * Parses APP_URL from the workspace .env and merges with
     * any additional hosts from config('turbo.docker.hosts').
     *
     * @return array<string>
     */
    public function resolveHosts(): array
    {
        $hosts = [];

        // Parse APP_URL from workspace .env
        $envPath = $this->workspace.'/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/^APP_URL=(.+)$/m', $envContent, $matches)) {
                $parsed = parse_url(trim($matches[1]));
                if (! empty($parsed['host'])) {
                    $hosts[] = $parsed['host'];
                }
            }
        }

        // Merge config hosts
        $configHosts = config('turbo.docker.hosts', []);
        $hosts = array_merge($hosts, $configHosts);

        return array_values(array_unique($hosts));
    }

    /**
     * Check if a sandbox with this name already exists.
     */
    public function sandboxExists(): bool
    {
        $process = new Process(['docker', 'sandbox', 'ls', '-q']);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        $names = array_filter(
            array_map('trim', explode("\n", $process->getOutput()))
        );

        return in_array($this->sandboxName(), $names, true);
    }

    /**
     * Create a process to create the sandbox from the built image.
     */
    public function createProcess(): Process
    {
        return $this->process([
            'docker', 'sandbox', 'create',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
        ]);
    }

    /**
     * Create a process to remove the sandbox.
     */
    public function removeProcess(): Process
    {
        return $this->process([
            'docker', 'sandbox', 'rm',
            $this->sandboxName(),
        ]);
    }

    /**
     * Ensure the sandbox exists, creating it if necessary.
     */
    public function ensureSandboxExists(): bool
    {
        if ($this->sandboxExists()) {
            return true;
        }

        $process = $this->createProcess();
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Create a process to build the sandbox image from the Dockerfile.
     */
    public function buildProcess(): Process
    {
        return $this->process([
            'docker', 'build',
            '--progress=quiet',
            '-t', $this->image,
            '-f', $this->dockerfile,
            dirname($this->dockerfile),
        ]);
    }

    /**
     * Execute a command inside the sandbox via docker sandbox exec.
     */
    public function execProcess(array $command): Process
    {
        return $this->process(array_merge([
            'docker', 'sandbox', 'exec',
            $this->sandboxName(),
            '--',
        ], $command));
    }

    /**
     * Create a TTY process for an interactive Claude session in the sandbox.
     *
     * @param  array<string>  $claudeArgs  Optional arguments to pass to Claude CLI
     */
    public function interactiveProcess(array $claudeArgs = []): Process
    {
        $this->ensureSandboxExists();

        $command = [
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
        ];

        if (! empty($claudeArgs)) {
            $command[] = '--';
            $command = array_merge($command, $claudeArgs);
        }

        return $this->ttyProcess($command);
    }

    /**
     * Create a PTY process to run a prompt in the sandbox.
     */
    public function promptProcess(string $prompt): Process
    {
        $this->ensureSandboxExists();

        return $this->ptyProcess([
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
            '--',
            '-p', $prompt,
        ]);
    }

    /**
     * Run a prompt in the sandbox, creating it if necessary.
     */
    public function runPrompt(string $prompt, ?callable $output = null): Process
    {
        return $this->runInSandbox(['-p', $prompt], $output);
    }

    /**
     * Run a Claude CLI command in the sandbox (e.g. plugin, config subcommands).
     *
     * Unlike runPrompt(), this passes arguments directly to the claude CLI
     * instead of using the -p flag, so they're treated as subcommands.
     */
    public function runCommand(array $args, ?callable $output = null): Process
    {
        return $this->runInSandbox($args, $output);
    }

    /**
     * Run arguments in the sandbox, creating it if it doesn't exist.
     *
     * @param  array<string>  $claudeArgs  Arguments passed to claude after --
     */
    protected function runInSandbox(array $claudeArgs, ?callable $output = null): Process
    {
        $this->ensureSandboxExists();

        $command = array_merge([
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
            '--',
        ], $claudeArgs);

        $process = $this->ptyProcess($command);
        $process->run($output);

        return $process;
    }

    /**
     * Get the path to the Dockerfile in the package.
     */
    protected function getPackageDockerfilePath(): string
    {
        return dirname(__DIR__, 2).'/Dockerfile';
    }

    /**
     * Create a process with no timeout.
     */
    protected function process(array $command): Process
    {
        $process = new Process($command);
        $process->setTimeout(null);

        return $process;
    }

    /**
     * Create a process with TTY for fully interactive sessions.
     *
     * TTY connects the real terminal's stdin/stdout/stderr directly,
     * allowing interactive programs like Claude to work properly.
     */
    protected function ttyProcess(array $command): Process
    {
        $process = $this->process($command);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    /**
     * Create a process with PTY for streaming output.
     *
     * PTY creates a pseudo-terminal for output capture without
     * connecting the real terminal's stdin.
     */
    protected function ptyProcess(array $command): Process
    {
        $process = $this->process($command);

        if (Process::isPtySupported()) {
            $process->setPty(true);
        }

        return $process;
    }
}
