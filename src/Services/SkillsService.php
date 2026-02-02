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
        return base_path('.ai/skills');
    }

    /**
     * Check if a skill exists in the target project.
     */
    public function existsInTarget(string $name): bool
    {
        return $this->files->isDirectory($this->getTargetSkillsPath().'/'.$name);
    }
}
