<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('reports sandbox not found when it does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->once()->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:doctor')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('runs host and port checks when sandbox exists', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->once()->andReturn(true);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');
    $sandbox->shouldReceive('resolveHosts')->once()->andReturn([]);
    $sandbox->shouldReceive('portsProcess')->once()->andReturn(
        new Symfony\Component\Process\Process(['echo', 'No ports'])
    );

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:doctor')
        ->expectsOutputToContain('exists');
});
