<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('returns the correct dockerfile path', function () {
    $sandbox = app(DockerSandbox::class);

    expect(basename($sandbox->dockerfile))->toBe('Dockerfile');
    expect(file_exists($sandbox->dockerfile))->toBeTrue();
});

it('uses static default image name', function () {
    $sandbox = app(DockerSandbox::class);

    expect($sandbox->image)->toBe('turbo');
});

it('derives sandbox name from workspace path', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);

    expect($sandbox->sandboxName())->toBe('claude-cpbc');
});

it('creates a build process with correct command', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('build')
        ->toContain('--progress=quiet')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('-f')
        ->toContain('Dockerfile');
});

it('creates an interactive process for a new sandbox', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);

    $process = $sandbox->interactiveProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('--load-local-template')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('--name')
        ->toContain('claude-cpbc')
        ->toContain('claude')
        ->toContain('/Users/dev/Sites/cpbc');
});

it('creates an interactive process for an existing sandbox', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);

    $process = $sandbox->interactiveProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('claude-cpbc')
        ->not->toContain('--load-local-template')
        ->not->toContain('/Users/dev/Sites/cpbc');
});

it('creates a prompt process for a new sandbox', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);

    $process = $sandbox->promptProcess('Hello Claude');

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('--load-local-template')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('--name')
        ->toContain('claude-cpbc')
        ->toContain('claude')
        ->toContain('/Users/dev/Sites/cpbc')
        ->toContain('--')
        ->toContain('-p')
        ->toContain('Hello Claude');
});

it('creates a prompt process for an existing sandbox', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);

    $process = $sandbox->promptProcess('Hello Claude');

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('claude-cpbc')
        ->toContain('--')
        ->toContain('-p')
        ->toContain('Hello Claude')
        ->not->toContain('--load-local-template')
        ->not->toContain('/Users/dev/Sites/cpbc');
});
