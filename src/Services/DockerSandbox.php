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
     * Create a TTY process for an interactive Claude session in the sandbox.
     */
    public function interactiveProcess(): Process
    {
        if ($this->sandboxExists()) {
            return $this->ttyProcess([
                'docker', 'sandbox', 'run',
                $this->sandboxName(),
            ]);
        }

        return $this->ttyProcess([
            'docker', 'sandbox', 'run',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
        ]);
    }

    /**
     * Create a PTY process to run a prompt in the sandbox.
     */
    public function promptProcess(string $prompt): Process
    {
        if ($this->sandboxExists()) {
            return $this->ptyProcess([
                'docker', 'sandbox', 'run',
                $this->sandboxName(),
                '--',
                '-p', $prompt,
            ]);
        }

        return $this->ptyProcess([
            'docker', 'sandbox', 'run',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
            '--',
            '-p', $prompt,
        ]);
    }

    /**
     * Run a prompt in the sandbox, handling existence detection automatically.
     *
     * Tries the existing sandbox first. If it doesn't exist, creates a new one.
     * This avoids relying on sandboxExists() which may not detect stopped sandboxes.
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
        if ($this->sandboxExists()) {
            $command = array_merge([
                'docker', 'sandbox', 'run',
                $this->sandboxName(),
                '--',
            ], $claudeArgs);
        } else {
            $command = array_merge([
                'docker', 'sandbox', 'run',
                '--load-local-template',
                '-t', $this->image,
                '--name', $this->sandboxName(),
                'claude',
                $this->workspace,
                '--',
            ], $claudeArgs);
        }

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
