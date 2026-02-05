<?php

namespace Springloaded\Turbo\Services;

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
     */
    public function sandboxName(): string
    {
        return "claude-{$this->image}";
    }

    /**
     * Check if a sandbox with this name already exists.
     */
    public function sandboxExists(): bool
    {
        $process = new Process(['docker', 'sandbox', 'ls', '--json']);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return collect(json_decode($process->getOutput(), true)['vms'] ?? [])
            ->pluck('name')
            ->contains($this->sandboxName());
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
     * Create a new sandbox from the local image template (interactive).
     */
    public function createSandbox(): Process
    {
        return $this->ttyProcess([
            'docker', 'sandbox', 'create',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
        ]);
    }

    /**
     * Run an existing sandbox (interactive).
     */
    public function runSandbox(): Process
    {
        return $this->ttyProcess([
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
        ]);
    }

    /**
     * Run a prompt in the sandbox (non-interactive, streamable output).
     *
     * Creates a new sandbox if one doesn't exist.
     *
     * @see https://docs.docker.com/ai/sandboxes/claude-code/#pass-a-prompt-directly
     */
    public function prompt(string $prompt): Process
    {
        return $this->ptyProcess(
            $this->sandboxExists()
                ? $this->promptCommandForExisting($prompt)
                : $this->promptCommandForNew($prompt)
        );
    }

    /**
     * Get the path to the Dockerfile in the package.
     */
    protected function getPackageDockerfilePath(): string
    {
        return dirname(__DIR__, 2).'/Dockerfile';
    }

    /**
     * Command to run a prompt in an existing sandbox.
     */
    protected function promptCommandForExisting(string $prompt): array
    {
        return [
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
            '--',
            ...explode(' ', $prompt),
        ];
    }

    /**
     * Command to create a new sandbox and run a prompt.
     */
    protected function promptCommandForNew(string $prompt): array
    {
        return [
            'docker', 'sandbox', 'run',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
            '--',
            '-p', $prompt,
        ];
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
     * Create a process with TTY for interactive sessions.
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
     */
    protected function ptyProcess(array $command): Process
    {
        $process = $this->process($command);

        if (Process::isTtySupported()) {
            $process->setPty(true);
        }

        return $process;
    }
}
