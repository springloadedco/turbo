<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('returns the correct dockerfile path', function () {
    $sandbox = app(DockerSandbox::class);

    expect(basename($sandbox->dockerfile))->toBe('Dockerfile');
    expect(file_exists($sandbox->dockerfile))->toBeTrue();
});

it('creates a build process with correct command', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('build')
        ->toContain('-t')
        ->toContain('turbo-sandbox')
        ->toContain('-f')
        ->toContain('Dockerfile');
});

it('creates an interactive process with workspace from config', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->interactiveProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('--template')
        ->toContain('turbo-sandbox')
        ->toContain('--workspace')
        ->toContain('/test/workspace')
        ->toContain('claude');
});

it('creates a prompt process with workspace from config', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->promptProcess('Hello Claude');

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('--template')
        ->toContain('turbo-sandbox')
        ->toContain('--workspace')
        ->toContain('/test/workspace')
        ->toContain('claude')
        ->toContain('-p')
        ->toContain('Hello Claude');
});
