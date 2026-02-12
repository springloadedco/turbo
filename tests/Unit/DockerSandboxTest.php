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
    $sandbox->shouldReceive('prepareSandbox')->once();

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
    $sandbox->shouldReceive('prepareSandbox')->once();

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

it('resolves hosts from APP_URL in workspace .env', function () {
    $workspace = sys_get_temp_dir().'/turbo-test-'.uniqid();
    mkdir($workspace);
    file_put_contents($workspace.'/.env', "APP_URL=http://grace-marketing-site.test\n");

    config()->set('turbo.docker.workspace', $workspace);
    config()->set('turbo.docker.hosts', []);

    $sandbox = app(DockerSandbox::class);
    $hosts = $sandbox->resolveHosts();

    expect($hosts)->toBe(['grace-marketing-site.test']);

    // Cleanup
    unlink($workspace.'/.env');
    rmdir($workspace);
});

it('merges config hosts with APP_URL host', function () {
    $workspace = sys_get_temp_dir().'/turbo-test-'.uniqid();
    mkdir($workspace);
    file_put_contents($workspace.'/.env', "APP_URL=http://app.test\n");

    config()->set('turbo.docker.workspace', $workspace);
    config()->set('turbo.docker.hosts', ['api.test']);

    $sandbox = app(DockerSandbox::class);
    $hosts = $sandbox->resolveHosts();

    expect($hosts)->toBe(['app.test', 'api.test']);

    unlink($workspace.'/.env');
    rmdir($workspace);
});

it('returns only config hosts when no .env exists', function () {
    $workspace = sys_get_temp_dir().'/turbo-test-'.uniqid();
    mkdir($workspace);

    config()->set('turbo.docker.workspace', $workspace);
    config()->set('turbo.docker.hosts', ['api.test']);

    $sandbox = app(DockerSandbox::class);
    $hosts = $sandbox->resolveHosts();

    expect($hosts)->toBe(['api.test']);

    rmdir($workspace);
});

it('returns empty array when no hosts configured and no .env', function () {
    $workspace = sys_get_temp_dir().'/turbo-test-'.uniqid();
    mkdir($workspace);

    config()->set('turbo.docker.workspace', $workspace);
    config()->set('turbo.docker.hosts', []);

    $sandbox = app(DockerSandbox::class);
    $hosts = $sandbox->resolveHosts();

    expect($hosts)->toBe([]);

    rmdir($workspace);
});

it('deduplicates hosts when APP_URL matches config entry', function () {
    $workspace = sys_get_temp_dir().'/turbo-test-'.uniqid();
    mkdir($workspace);
    file_put_contents($workspace.'/.env', "APP_URL=http://app.test\n");

    config()->set('turbo.docker.workspace', $workspace);
    config()->set('turbo.docker.hosts', ['app.test', 'api.test']);

    $sandbox = app(DockerSandbox::class);
    $hosts = $sandbox->resolveHosts();

    expect($hosts)->toBe(['app.test', 'api.test']);

    unlink($workspace.'/.env');
    rmdir($workspace);
});

it('creates proxy bypass process for a host on port 80', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->proxyBypassProcess('app.test');

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('network')
        ->toContain('proxy')
        ->toContain('claude-cpbc')
        ->toContain('--bypass-host')
        ->toContain('app.test:80');
});

it('preserves explicit port in proxy bypass', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->proxyBypassProcess('api.test:8080');

    $commandLine = $process->getCommandLine();
    expect($commandLine)->toContain('api.test:8080');
});

it('creates prepareSandboxProcess with workspace and host entries', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('resolveHosts')->andReturn(['app.test']);
    $sandbox->shouldReceive('resolveHostIp')->andReturn('192.168.65.254');

    $process = $sandbox->prepareSandboxProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('sandbox')
        ->toContain('exec')
        ->toContain('setup-sandbox')
        ->toContain('/Users/dev/Sites/cpbc')
        ->toContain('app.test:192.168.65.254');
});

it('creates prepareSandboxProcess without host entries when none configured', function () {
    config()->set('turbo.docker.workspace', '/Users/dev/Sites/cpbc');

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('resolveHosts')->andReturn([]);

    $process = $sandbox->prepareSandboxProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('setup-sandbox')
        ->toContain('/Users/dev/Sites/cpbc')
        ->not->toContain(':192');
});
