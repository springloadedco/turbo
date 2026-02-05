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
        return 'turbo-'.Str::slug(basename($this->workspace));
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
     * Create a PTY process for an interactive Claude session in the sandbox.
     */
    public function interactiveProcess(): Process
    {
        if ($this->sandboxExists()) {
            return $this->ptyProcess([
                'docker', 'sandbox', 'run',
                $this->sandboxName(),
            ]);
        }

        return $this->ptyProcess([
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
