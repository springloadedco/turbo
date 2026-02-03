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
        $this->dockerfile = config('turbo.docker.dockerfile');
        $this->workspace = config('turbo.docker.workspace');
    }

    /**
     * Create a process to build the sandbox image from the Dockerfile.
     */
    public function buildProcess(): Process
    {
        $process = new Process([
            'docker', 'build',
            '--progress=plain',
            '-t', $this->image,
            '-f', $this->dockerfile,
            dirname($this->dockerfile),
        ]);

        $process->setTimeout(null);

        return $process;
    }

    /**
     * Create a process to run sandbox in interactive TTY mode.
     */
    public function interactiveProcess(): Process
    {

        $process = new Process([
            'docker', 'sandbox', 'run',
            '--template', $this->image,
            '--workspace', $this->workspace,
            'claude',
            '--dangerously-skip-permissions',
        ]);

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    /**
     * Create a process to run sandbox with a prompt (non-interactive).
     */
    public function promptProcess(string $prompt): Process
    {
        $process = new Process([
            'docker', 'sandbox', 'run',
            '--template', $this->image,
            '--workspace', $this->workspace,
            'claude',
            '-p', $prompt,
        ]);

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setPty(true);
        }

        return $process;
    }
}
