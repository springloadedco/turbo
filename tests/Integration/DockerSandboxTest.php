<?php

use Springloaded\Turbo\Services\DockerSandbox;

beforeEach(function () {
    if (! dockerIsAvailable()) {
        $this->markTestSkipped('Docker is not available.');
    }
});

it('can build the sandbox image', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $process->run();

    expect($process->isSuccessful())->toBeTrue();
})->skip(! dockerIsAvailable(), 'Docker is not available');

it('can run the sandbox in detached mode', function () {
    $sandbox = app(DockerSandbox::class);

    // First ensure the image is built
    $sandbox->buildProcess()->run();

    // Run in detached mode
    $process = $sandbox->detachedProcess(base_path());
    $process->run();

    expect($process->isSuccessful())->toBeTrue();
})->skip(! dockerIsAvailable(), 'Docker is not available');

function dockerIsAvailable(): bool
{
    $process = new Symfony\Component\Process\Process(['docker', 'info']);
    $process->run();

    return $process->isSuccessful();
}
