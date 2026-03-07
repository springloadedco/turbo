<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

/**
 * Integration tests that verify the MCP tool correctly reads feedback
 * images written to the workspace using the real local Storage disk.
 */

beforeEach(function () {
    $this->disk = Storage::disk('local');
    $this->feedbackDir = 'agent-captures/feedback';

    // Ensure a clean state before each test
    if ($this->disk->exists($this->feedbackDir)) {
        $this->disk->deleteDirectory($this->feedbackDir);
    }
});

afterEach(function () {
    // Clean up after each test
    if ($this->disk->exists($this->feedbackDir)) {
        $this->disk->deleteDirectory($this->feedbackDir);
    }
});

it('returns feedback images written to workspace', function () {
    // Simulate what the native messaging host writes: a PNG file + JSON manifest
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $manifest = [
        'url' => 'https://example.com/dashboard',
        'annotation' => 'Header alignment is off by 4px',
        'viewport' => ['width' => 1440, 'height' => 900],
        'timestamp' => '2026-03-07T14:30:00Z',
    ];

    $this->disk->put("{$this->feedbackDir}/capture-001.png", $pngData);
    $this->disk->put("{$this->feedbackDir}/capture-001.png.json", json_encode($manifest));

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)->toBeArray()->toHaveCount(2);

    // First response: text metadata
    $text = (string) $result[0]->content();
    expect($text)
        ->toContain('capture-001.png')
        ->toContain('https://example.com/dashboard')
        ->toContain('Header alignment is off by 4px')
        ->toContain('1440x900')
        ->toContain('2026-03-07T14:30:00Z');

    // Second response: the image data
    expect($result[1])->toBeInstanceOf(Response::class);
    expect((string) $result[1]->content())->toBe($pngData);
});

it('returns empty message when no feedback exists', function () {
    // Directory does not exist at all
    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)
        ->toBeInstanceOf(Response::class);
    expect((string) $result->content())
        ->toBe('No developer feedback screenshots found.');
});

it('returns images in chronological order', function () {
    // Write two images with different timestamp-based names, inserted out of order
    foreach (['capture-002', 'capture-001'] as $name) {
        $this->disk->put(
            "{$this->feedbackDir}/{$name}.png",
            "png-data-{$name}"
        );
        $this->disk->put(
            "{$this->feedbackDir}/{$name}.png.json",
            json_encode([
                'url' => "https://example.com/{$name}",
                'annotation' => "Note for {$name}",
                'viewport' => ['width' => 1920, 'height' => 1080],
                'timestamp' => "2026-03-07T10:0{$name[-1]}:00Z",
            ])
        );
    }

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    // 2 screenshots x 2 responses each = 4 items
    expect($result)->toBeArray()->toHaveCount(4);

    // Oldest (capture-001) should come first
    $text1 = (string) $result[0]->content();
    $text2 = (string) $result[2]->content();

    expect($text1)->toContain('capture-001');
    expect($text2)->toContain('capture-002');
});

it('skips images without a JSON manifest', function () {
    // Write a PNG without a companion .json file
    $this->disk->put("{$this->feedbackDir}/orphan.png", 'orphan-png-data');

    // Write a valid pair so we can verify partial results
    $this->disk->put("{$this->feedbackDir}/valid.png", 'valid-png-data');
    $this->disk->put(
        "{$this->feedbackDir}/valid.png.json",
        json_encode([
            'url' => 'https://example.com/valid',
            'annotation' => 'This one has a manifest',
            'viewport' => ['width' => 1920, 'height' => 1080],
            'timestamp' => '2026-03-07T12:00:00Z',
        ])
    );

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    // Only the valid pair should be returned (text + image = 2 responses)
    expect($result)->toBeArray()->toHaveCount(2);

    $text = (string) $result[0]->content();
    expect($text)
        ->toContain('valid.png')
        ->not->toContain('orphan');
});
