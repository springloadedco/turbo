<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('fails when sandbox does not exist', function () {
    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->workspace = '/Users/dev/Sites/cpbc';
    $sandbox->shouldReceive('sandboxExists')->andReturn(false);
    $sandbox->shouldReceive('sandboxName')->andReturn('claude-cpbc');

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])->assertFailed();
});

it('runs claude mcp add inside the sandbox with the configured callback port', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(true);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')
        ->withArgs(function (array $command) {
            return $command === [
                'claude', 'mcp', 'add',
                '--transport', 'http',
                '--callback-port', '33418',
                '--scope', 'user',
                'figma', 'https://mcp.figma.com/mcp',
            ];
        })
        ->once()
        ->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])
        ->expectsOutputToContain("MCP server 'figma' registered with OAuth callback port 33418")
        ->assertSuccessful();
});

it('honours --scope and --transport options', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(true);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')
        ->withArgs(function (array $command) {
            return in_array('--scope', $command, true)
                && in_array('project', $command, true)
                && in_array('--transport', $command, true)
                && in_array('sse', $command, true);
        })
        ->once()
        ->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
        '--scope' => 'project',
        '--transport' => 'sse',
    ])->assertSuccessful();
});

it('reports failure when claude mcp add exits non-zero', function () {
    config()->set('turbo.oauth.callback_port', 33418);

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(false);

    $sandbox = Mockery::mock(DockerSandbox::class)->makePartial();
    $sandbox->shouldReceive('sandboxExists')->andReturn(true);
    $sandbox->shouldReceive('execProcess')->andReturn($process);

    app()->instance(DockerSandbox::class, $sandbox);

    $this->artisan('turbo:mcp:add', [
        'name' => 'figma',
        'url' => 'https://mcp.figma.com/mcp',
    ])->assertFailed();
});
