<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('wraps the command string in bash -c', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')
        ->once()
        ->withArgs(function (array $args) {
            return $args === ['bash', '-c', 'ls -la'];
        })
        ->andReturn(new Process(['echo', 'ok']));

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:exec', ['cmd' => 'ls -la'])
        ->assertSuccessful();
});

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:exec', ['cmd' => 'ls -la'])
        ->assertFailed();
});
