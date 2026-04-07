<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('succeeds when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:rm', ['--force' => true])
        ->assertSuccessful();
});

it('removes sandbox with force flag', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');
    $sandbox->shouldReceive('removeProcess')->once()->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:rm', ['--force' => true])
        ->assertSuccessful();
});
