<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:ports')
        ->assertFailed();
});

it('rejects publish and unpublish together', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:ports', ['--publish' => '8080:8000', '--unpublish' => '8080:8000'])
        ->expectsOutput('Cannot use --publish and --unpublish together.')
        ->assertFailed();
});

it('calls portsProcess when listing', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('portsProcess')->once()->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:ports')
        ->assertSuccessful();
});

it('calls publishPortProcess with spec', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('publishPortProcess')->once()->with('8080:8000')->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:ports', ['--publish' => '8080:8000'])
        ->assertSuccessful();
});

it('calls unpublishPortProcess with spec', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('unpublishPortProcess')->once()->with('8080:8000')->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:ports', ['--unpublish' => '8080:8000'])
        ->assertSuccessful();
});
