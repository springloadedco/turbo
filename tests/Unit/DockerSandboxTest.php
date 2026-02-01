<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('returns the correct dockerfile path', function () {
    $sandbox = app(DockerSandbox::class);

    expect($sandbox->dockerfile)->toEndWith('/Dockerfile');
    expect(file_exists($sandbox->dockerfile))->toBeTrue();
});

it('creates a build process with correct command', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    expect($process->getCommandLine())
        ->toContain("'docker'")
        ->toContain("'build'")
        ->toContain("'-t'")
        ->toContain("'turbo-sandbox'")
        ->toContain("'-f'")
        ->toContain('Dockerfile');
});

it('creates an interactive process with workspace from config', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->interactiveProcess();

    expect($process->getCommandLine())
        ->toContain("'docker'")
        ->toContain("'sandbox'")
        ->toContain("'run'")
        ->toContain("'--template'")
        ->toContain("'turbo-sandbox'")
        ->toContain("'--workspace'")
        ->toContain('/test/workspace')
        ->toContain("'claude'");
});

it('creates an interactive process with custom workspace', function () {
    config()->set('turbo.docker.workspace', '/default/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->interactiveProcess('/custom/workspace');

    expect($process->getCommandLine())
        ->toContain('--workspace')
        ->toContain('/custom/workspace')
        ->not->toContain('/default/workspace');
});

it('creates a detached process with workspace from config', function () {
    config()->set('turbo.docker.workspace', '/test/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->detachedProcess();

    expect($process->getCommandLine())
        ->toContain("'docker'")
        ->toContain("'sandbox'")
        ->toContain("'run'")
        ->toContain("'--template'")
        ->toContain("'turbo-sandbox'")
        ->toContain("'--workspace'")
        ->toContain('/test/workspace')
        ->toContain("'--detached'")
        ->toContain("'claude'");
});

it('creates a detached process with custom workspace', function () {
    config()->set('turbo.docker.workspace', '/default/workspace');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->detachedProcess('/custom/workspace');

    expect($process->getCommandLine())
        ->toContain('--workspace')
        ->toContain('/custom/workspace')
        ->not->toContain('/default/workspace');
});
