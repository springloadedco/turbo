<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

beforeEach(function () {
    Storage::fake('local');
});

it('returns no feedback message when directory does not exist', function () {
    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('No developer feedback screenshots found.');
});

it('returns no feedback message when directory is empty', function () {
    Storage::disk('local')->makeDirectory('agent-captures/feedback');

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('No developer feedback screenshots found.');
});

it('returns text and image responses for a single screenshot', function () {
    $manifest = [
        'url' => 'https://example.com/page',
        'annotation' => 'The button is misaligned',
        'viewport' => ['width' => 1920, 'height' => 1080],
        'timestamp' => '2026-03-07T10:00:00Z',
    ];

    Storage::disk('local')->put(
        'agent-captures/feedback/screenshot-001.png',
        'fake-png-data'
    );
    Storage::disk('local')->put(
        'agent-captures/feedback/screenshot-001.png.json',
        json_encode($manifest)
    );

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)->toBeArray()->toHaveCount(2);

    // First item: text metadata
    expect($result[0])->toBeInstanceOf(Response::class);
    $text = (string) $result[0]->content();
    expect($text)->toContain('https://example.com/page');
    expect($text)->toContain('The button is misaligned');
    expect($text)->toContain('1920');
    expect($text)->toContain('1080');

    // Second item: image
    expect($result[1])->toBeInstanceOf(Response::class);
    expect((string) $result[1]->content())->toBe('fake-png-data');
});

it('returns screenshots sorted chronologically oldest first', function () {
    // Create screenshots with different timestamps in the filenames
    // Files are sorted by name, which is chronological due to timestamp prefix
    foreach (['screenshot-003', 'screenshot-001', 'screenshot-002'] as $name) {
        Storage::disk('local')->put(
            "agent-captures/feedback/{$name}.png",
            "data-{$name}"
        );
        Storage::disk('local')->put(
            "agent-captures/feedback/{$name}.png.json",
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

    // 3 screenshots x 2 responses each = 6 items
    expect($result)->toBeArray()->toHaveCount(6);

    // Verify order: screenshot-001, screenshot-002, screenshot-003
    $text1 = (string) $result[0]->content();
    $text2 = (string) $result[2]->content();
    $text3 = (string) $result[4]->content();

    expect($text1)->toContain('screenshot-001');
    expect($text2)->toContain('screenshot-002');
    expect($text3)->toContain('screenshot-003');
});

it('skips png files without a companion json manifest', function () {
    Storage::disk('local')->put(
        'agent-captures/feedback/no-manifest.png',
        'orphan-png'
    );

    $tool = new GetDeveloperFeedback;
    $result = $tool->handle(new Request);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('No developer feedback screenshots found.');
});

it('has correct name and description', function () {
    $tool = new GetDeveloperFeedback;

    expect($tool->name())->toBe('get-developer-feedback');
    expect($tool->description())->toContain('feedback');
});

it('has an empty schema', function () {
    $tool = new GetDeveloperFeedback;

    $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
    expect($tool->schema($schema))->toBe([]);
});
