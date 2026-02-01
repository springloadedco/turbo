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

    $output = $process->getOutput().$process->getErrorOutput();
    expect($output)
        ->toContain('exporting to image')
        ->toContain('writing image sha256:')
        ->toContain('naming to docker.io/library/'.$sandbox->image);
})->skip(! dockerIsAvailable(), 'Docker is not available');

function dockerIsAvailable(): bool
{
    $process = new Symfony\Component\Process\Process(['docker', 'info']);
    $process->run();

    return $process->isSuccessful();
}
