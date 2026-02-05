<?php

namespace Springloaded\Turbo\Services;

use Illuminate\Filesystem\Filesystem;

class FeedbackLoopDetector
{
    /** @var array<string> */
    protected array $composerKeys = ['test', 'lint', 'analyse', 'analyze', 'format', 'types'];

    /** @var array<string> */
    protected array $npmKeys = ['build', 'lint', 'test', 'types', 'format'];

    public function __construct(
        protected Filesystem $files,
    ) {}

    /**
     * Detect available feedback loop commands from project config files.
     *
     * @return array<string>
     */
    public function detect(): array
    {
        return array_values(array_merge(
            $this->detectComposer(),
            $this->detectNpm(),
        ));
    }

    /**
     * @return array<string>
     */
    protected function detectComposer(): array
    {
        $path = base_path('composer.json');

        if (! $this->files->exists($path)) {
            return [];
        }

        $scripts = json_decode($this->files->get($path), true)['scripts'] ?? [];

        return collect($this->composerKeys)
            ->filter(fn (string $key) => isset($scripts[$key]))
            ->map(fn (string $key) => "composer {$key}")
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    protected function detectNpm(): array
    {
        $path = base_path('package.json');

        if (! $this->files->exists($path)) {
            return [];
        }

        $scripts = json_decode($this->files->get($path), true)['scripts'] ?? [];

        return collect($this->npmKeys)
            ->filter(fn (string $key) => isset($scripts[$key]))
            ->map(fn (string $key) => "npm run {$key}")
            ->values()
            ->all();
    }
}
