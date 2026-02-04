<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('returns the correct dockerfile path', function () {
    $sandbox = app(DockerSandbox::class);

    expect(basename($sandbox->dockerfile))->toBe('Dockerfile');
    expect(file_exists($sandbox->dockerfile))->toBeTrue();
});

it('returns the correct sandbox name', function () {
    $sandbox = app(DockerSandbox::class);

    expect($sandbox->sandboxName())->toBe('claude-turbo');
});

it('creates a build process with correct command', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('build')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('-f')
        ->toContain('Dockerfile');
});

it('returns the correct build command', function () {
    $sandbox = app(DockerSandbox::class);
    $command = $sandbox->buildCommand();

    expect($command[0])->toBe('docker');
    expect($command[1])->toBe('build');
    expect($command[2])->toBe('-t');
    expect($command[3])->toBe('turbo');
    expect($command[4])->toBe('-f');
    expect($command[5])->toContain('Dockerfile');
});

it('creates a sandbox with --name flag', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->createSandbox();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('create')
        ->toContain('--load-local-template')
        ->toContain("'-t'")
        ->toContain('turbo')
        ->toContain('--name')
        ->toContain('claude-turbo')
        ->toContain('claude')
        ->toContain('/test/workspace');
});

it('runs an existing sandbox by name', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->runSandbox();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('claude-turbo');
});

it('creates a prompt process that creates a new sandbox when none exists', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->promptProcess('Hello Claude');

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('--load-local-template')
        ->toContain('turbo')
        ->toContain('--name')
        ->toContain('claude-turbo')
        ->toContain('claude')
        ->toContain('/test/workspace')
        ->toContain('--')
        ->toContain('Hello Claude');
});
