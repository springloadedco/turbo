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

it('creates a create process with correct command', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->createProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('create')
        ->toContain('--load-local-template')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('--name')
        ->toContain('claude-cpbc')
        ->toContain('claude')
        ->toContain('/Users/dev/Sites/cpbc');
});

it('creates a remove process with correct command', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->removeProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('rm')
        ->toContain('claude-cpbc');
});

it('ensureSandboxExists returns true when sandbox already exists', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldNotReceive('createProcess');

    expect($sandbox->ensureSandboxExists())->toBeTrue();
});

it('ensureSandboxExists creates sandbox when it does not exist', function () {
    $mockProcess = Mockery::mock(Symfony\Component\Process\Process::class);
    $mockProcess->shouldReceive('run')->once();
    $mockProcess->shouldReceive('isSuccessful')->andReturn(true);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('createProcess')->andReturn($mockProcess);

    expect($sandbox->ensureSandboxExists())->toBeTrue();
});

it('ensureSandboxExists returns false when creation fails', function () {
    $mockProcess = Mockery::mock(Symfony\Component\Process\Process::class);
    $mockProcess->shouldReceive('run')->once();
    $mockProcess->shouldReceive('isSuccessful')->andReturn(false);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('createProcess')->andReturn($mockProcess);

    expect($sandbox->ensureSandboxExists())->toBeFalse();
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


it('creates an exec process with correct command', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->execProcess(['bash', '-c', 'echo hello']);

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('exec')
        ->toContain('claude-cpbc')
        ->toContain('--')
        ->toContain('bash')
        ->toContain('echo hello');
});

it('interactiveProcess ensures sandbox exists and returns simple run command', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('ensureSandboxExists')->once()->andReturn(true);

    $process = $sandbox->interactiveProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('run')
        ->toContain('claude-cpbc')
        ->not->toContain('--load-local-template')
        ->not->toContain('create');
});

it('promptProcess ensures sandbox exists and returns simple run command', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->image = 'turbo';
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('ensureSandboxExists')->once()->andReturn(true);

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
        ->not->toContain('create');
});
