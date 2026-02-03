<?php

namespace Springloaded\Turbo\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

class SkillsService
{
    public function __construct(
        protected Filesystem $files,
    ) {}

    /**
     * Discover available skills from the package's .ai/skills directory.
     *
     * @return Collection<int, array{name: string, description: string, path: string}>
     */
    public function discover(): Collection
    {
        $skillsPath = $this->getPackageSkillsPath();

        if (! $this->files->isDirectory($skillsPath)) {
            return collect();
        }

        return collect($this->files->directories($skillsPath))
            ->map(fn ($path) => $this->parseSkill($path))
            ->filter()
            ->values();
    }

    /**
     * Get a specific skill by name.
     *
     * @return array{name: string, description: string, path: string}|null
     */
    public function find(string $name): ?array
    {
        return $this->discover()->firstWhere('name', $name);
    }

    /**
     * Get multiple skills by name.
     *
     * @param  array<string>  $names
     * @return Collection<int, array{name: string, description: string, path: string}>
     */
    public function findMany(array $names): Collection
    {
        return $this->discover()->filter(
            fn ($skill) => in_array($skill['name'], $names)
        )->values();
    }

    /**
     * Parse a skill directory into skill metadata.
     *
     * @return array{name: string, description: string, path: string}|null
     */
    protected function parseSkill(string $path): ?array
    {
        $skillMdPath = $path.'/SKILL.md';

        if (! $this->files->exists($skillMdPath)) {
            return null;
        }

        $content = $this->files->get($skillMdPath);
        $frontmatter = $this->parseFrontmatter($content);

        return [
            'name' => $frontmatter['name'] ?? basename($path),
            'description' => $frontmatter['description'] ?? '',
            'path' => $path,
        ];
    }

    /**
     * Parse YAML frontmatter from SKILL.md content.
     *
     * @return array<string, mixed>
     */
    public function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            return [];
        }

        try {
            return Yaml::parse($matches[1]) ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get the package's skills directory path.
     */
    public function getPackageSkillsPath(): string
    {
        return dirname(__DIR__, 2).'/.ai/skills';
    }

    /**
     * Get the target project's skills directory path.
     */
    public function getTargetSkillsPath(): string
    {
        return base_path('.claude/skills');
    }

    /**
     * Check if a skill exists in the target project.
     */
    public function existsInTarget(string $name): bool
    {
        return $this->files->isDirectory($this->getTargetSkillsPath().'/'.$name);
    }

    /**
     * Process template placeholders in content.
     */
    public function processTemplate(string $content): string
    {
        $feedbackLoops = $this->getFeedbackLoops();

        $replacements = [
            '{{ $feedback_loops }}' => $this->formatInline($feedbackLoops),
            '{{ $feedback_loops_checklist }}' => $this->formatChecklist($feedbackLoops),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Format commands as inline code list: `cmd1`, `cmd2`, `cmd3`
     *
     * @param  array<string>  $commands
     */
    public function formatInline(array $commands): string
    {
        return implode(', ', array_map(fn ($cmd) => "`{$cmd}`", $commands));
    }

    /**
     * Format commands as markdown checklist.
     *
     * @param  array<string>  $commands
     */
    public function formatChecklist(array $commands): string
    {
        return implode("\n", array_map(fn ($cmd) => "- [ ] `{$cmd}` passes", $commands));
    }

    /**
     * Get configured feedback loop commands.
     *
     * @return array<string>
     */
    public function getFeedbackLoops(): array
    {
        return config('turbo.feedback_loops', $this->getDefaultFeedbackLoops());
    }

    /**
     * Get default feedback loop commands.
     *
     * @return array<string>
     */
    public function getDefaultFeedbackLoops(): array
    {
        return [
            'composer lint',
            'composer test',
            'composer analyse',
            'npm run lint',
            'npm run types',
            'npm run build',
            'npm run test',
        ];
    }
}
