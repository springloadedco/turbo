<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:stop')
        ->assertFailed();
});

it('stops sandbox via stopProcess', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');
    $sandbox->shouldReceive('stopProcess')->once()->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:stop')
        ->assertSuccessful();
});
