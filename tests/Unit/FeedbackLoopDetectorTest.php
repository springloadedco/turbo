<?php

use Illuminate\Filesystem\Filesystem;
use Springloaded\Turbo\Services\FeedbackLoopDetector;

it('detects composer scripts', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn(true);
    $files->shouldReceive('get')->with(base_path('composer.json'))->andReturn(json_encode([
        'scripts' => [
            'test' => 'pest',
            'lint' => 'pint',
            'analyse' => 'phpstan',
            'post-update-cmd' => 'something',
        ],
    ]));
    $files->shouldReceive('exists')->with(base_path('package.json'))->andReturn(false);

    $detector = new FeedbackLoopDetector($files);

    expect($detector->detect())->toBe([
        'composer test',
        'composer lint',
        'composer analyse',
    ]);
});

it('detects npm scripts', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn(false);
    $files->shouldReceive('exists')->with(base_path('package.json'))->andReturn(true);
    $files->shouldReceive('get')->with(base_path('package.json'))->andReturn(json_encode([
        'scripts' => [
            'build' => 'vite build',
            'lint' => 'eslint .',
            'types' => 'tsc --noEmit',
            'dev' => 'vite',
        ],
    ]));

    $detector = new FeedbackLoopDetector($files);

    expect($detector->detect())->toBe([
        'npm run build',
        'npm run lint',
        'npm run types',
    ]);
});

it('detects both composer and npm scripts', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn(true);
    $files->shouldReceive('get')->with(base_path('composer.json'))->andReturn(json_encode([
        'scripts' => ['test' => 'pest', 'lint' => 'pint'],
    ]));
    $files->shouldReceive('exists')->with(base_path('package.json'))->andReturn(true);
    $files->shouldReceive('get')->with(base_path('package.json'))->andReturn(json_encode([
        'scripts' => ['build' => 'vite build', 'test' => 'vitest'],
    ]));

    $detector = new FeedbackLoopDetector($files);

    expect($detector->detect())->toBe([
        'composer test',
        'composer lint',
        'npm run build',
        'npm run test',
    ]);
});

it('returns empty array when no files exist', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn(false);
    $files->shouldReceive('exists')->with(base_path('package.json'))->andReturn(false);

    $detector = new FeedbackLoopDetector($files);

    expect($detector->detect())->toBe([]);
});

it('returns empty array when scripts key is missing', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn(true);
    $files->shouldReceive('get')->with(base_path('composer.json'))->andReturn(json_encode([
        'require' => [],
    ]));
    $files->shouldReceive('exists')->with(base_path('package.json'))->andReturn(false);

    $detector = new FeedbackLoopDetector($files);

    expect($detector->detect())->toBe([]);
});
