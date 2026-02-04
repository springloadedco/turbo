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
     * Get the path to the Dockerfile in the package.
     */
    protected function getPackageDockerfilePath(): string
    {
        return dirname(__DIR__, 2).'/Dockerfile';
    }

    /**
     * Get the command array to build the sandbox image.
     */
    public function buildCommand(): array
    {
        return [
            'docker', 'build',
            '--progress=plain',
            '-t', $this->image,
            '-f', $this->dockerfile,
            dirname($this->dockerfile),
        ];
    }

    /**
     * Create a process to build the sandbox image from the Dockerfile.
     */
    public function buildProcess(): Process
    {
        $process = new Process($this->buildCommand());

        $process->setTimeout(null);

        return $process;
    }

    /**
     * Check if a sandbox with this name already exists.
     */
    public function sandboxExists(): bool
    {
        $process = new Process([
            'docker', 'sandbox', 'ls',
            '--json',
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return collect(json_decode($process->getOutput(), true)['vms'] ?? [])
            ->pluck('name')
            ->contains($this->sandboxName());
    }

    /**
     * Create a new sandbox from the local image template.
     */
    public function createSandbox(): Process
    {
        $process = new Process([
            'docker', 'sandbox', 'create',
            '--load-local-template',
            '-t', $this->image,
            '--name', $this->sandboxName(),
            'claude',
            $this->workspace,
        ]);

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    /**
     * Run an existing sandbox by name (interactive session).
     */
    public function runSandbox(): Process
    {
        $process = new Process([
            'docker', 'sandbox', 'run',
            $this->sandboxName(),
        ]);

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    /**
     * Create a process to run sandbox with a prompt (non-interactive).
     *
     * Reuses an existing sandbox if one is found, otherwise creates a new one.
     * Uses PTY (pseudo-terminal) instead of TTY so the calling process can
     * capture and stream the output back to the user's terminal.
     *
     * @see https://docs.docker.com/ai/sandboxes/claude-code/#pass-a-prompt-directly
     */
    public function promptProcess(string $prompt): Process
    {
        if ($this->sandboxExists()) {
            $command = [
                'docker', 'sandbox', 'run',
                $this->sandboxName(),
                '--', $prompt,
            ];
        } else {
            $command = [
                'docker', 'sandbox', 'run',
                '--load-local-template',
                '-t', $this->image,
                '--name', $this->sandboxName(),
                'claude',
                $this->workspace,
                '--', $prompt,
            ];
        }

        $process = new Process($command);
        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setPty(true);
        }

        return $process;
    }
}
