<?php

namespace Springloaded\Turbo\Services;

use Symfony\Component\Process\Process;

class DockerSandbox
{
    public string $image;

    public string $dockerfile;

    public function __construct()
    {
        $this->image = config('turbo.docker.image');
        $this->dockerfile = config('turbo.docker.dockerfile');
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
    public function interactiveProcess(?string $workspace = null): Process
    {
        $workspace ??= config('turbo.docker.workspace');

        $process = new Process([
            'docker', 'sandbox', 'run',
            '--template', $this->image,
            '--workspace', $workspace,
            'claude',
        ]);

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    /**
     * Create a process to run sandbox in detached mode.
     */
    public function detachedProcess(?string $workspace = null): Process
    {
        $workspace ??= config('turbo.docker.workspace');

        $process = new Process([
            'docker', 'sandbox', 'run',
            '--template', $this->image,
            '--workspace', $workspace,
            '--detached',
            'claude',
        ]);

        $process->setTimeout(null);

        return $process;
    }
}
