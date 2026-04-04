<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('can build and push the sandbox image', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $process->run();

    expect($process->isSuccessful())->toBeTrue();
})->skip(
    ! dockerIsAvailable() || ! getenv('RUN_DOCKER_PUSH_TESTS'),
    'Docker push tests require RUN_DOCKER_PUSH_TESTS=1 and registry auth'
);

function dockerIsAvailable(): bool
{
    $process = new Process(['docker', 'info']);
    $process->run();

    return $process->isSuccessful();
}
