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

it('calls prepareSandbox and prints the OAuth callback port when sandbox exists', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('prepareSandbox')->once()->andReturn(null);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:prepare')
        ->expectsOutputToContain('OAuth callback relay listening on localhost:33418')
        ->assertSuccessful();
});

it('prints a warning when prepareSandbox returns one', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('prepareSandbox')->once()->andReturn('Could not publish OAuth callback port 33418: port already in use');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:prepare')
        ->expectsOutputToContain('Could not publish OAuth callback port 33418')
        ->expectsOutputToContain('OAuth callback relay listening on localhost:33418')
        ->assertSuccessful();
});
