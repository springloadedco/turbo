<?php

use Springloaded\Turbo\Services\DockerSandbox;

it('can build the sandbox image', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $process->run();

    expect($process->isSuccessful())->toBeTrue();

    $output = $process->getOutput().$process->getErrorOutput();
    expect($output)
        ->toContain('exporting to image')
        ->toContain('writing image sha256:')
        ->toContain('naming to docker.io/library/'.$sandbox->image);
})->skip(! dockerIsAvailable() || ! getenv('RUN_DOCKER_TESTS'), 'Docker integration tests require RUN_DOCKER_TESTS=1');

function dockerIsAvailable(): bool
{
    $process = new Symfony\Component\Process\Process(['docker', 'info']);
    $process->run();

    return $process->isSuccessful();
}
