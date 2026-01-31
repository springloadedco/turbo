<?php

namespace Springloaded\Turbo\Services;

use Symfony\Component\Process\Process;

class DockerSandbox
{
    protected string $image = 'turbo-sandbox';

    public function dockerfilePath(): string
    {
        return dirname(__DIR__, 2).'/Dockerfile';
    }

    /**
     * Create a process to build the sandbox image from the Dockerfile.
     */
    public function buildProcess(): Process
    {
        $process = new Process([
            'docker', 'build',
            '-t', $this->image,
            '-f', $this->dockerfilePath(),
            dirname($this->dockerfilePath()),
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
