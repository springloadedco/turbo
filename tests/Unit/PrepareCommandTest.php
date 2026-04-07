<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:prepare')
        ->assertFailed();
});

it('calls prepareSandbox when sandbox exists', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('prepareSandbox')->once();

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:prepare')
        ->assertSuccessful();
});
