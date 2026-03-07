<?php

declare(strict_types=1);

namespace Springloaded\Turbo\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetDeveloperFeedback extends Tool
{
    protected string $name = 'get-developer-feedback';

    protected string $description = 'Retrieves developer feedback screenshots and annotations captured from the browser.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return Response|array<int, Response>
     */
    public function handle(Request $request): Response|array
    {
        $disk = Storage::disk('local');
        $directory = 'agent-captures/feedback';

        if (! $disk->exists($directory)) {
            return Response::text('No developer feedback screenshots found.');
        }

        $files = collect($disk->files($directory))
            ->filter(fn (string $file): bool => str_ends_with($file, '.png'))
            ->filter(fn (string $file): bool => $disk->exists($file.'.json'))
            ->sort()
            ->values();

        if ($files->isEmpty()) {
            return Response::text('No developer feedback screenshots found.');
        }

        $responses = [];

        foreach ($files as $file) {
            $manifest = json_decode($disk->get($file.'.json'), true);

            $responses[] = Response::text(implode("\n", [
                "Screenshot: ".basename($file),
                "URL: ".($manifest['url'] ?? 'unknown'),
                "Annotation: ".($manifest['annotation'] ?? 'none'),
                "Viewport: ".($manifest['viewport']['width'] ?? '?')."x".($manifest['viewport']['height'] ?? '?'),
                "Timestamp: ".($manifest['timestamp'] ?? 'unknown'),
            ]));

            $responses[] = Response::image($disk->get($file));
        }

        return $responses;
    }
}
