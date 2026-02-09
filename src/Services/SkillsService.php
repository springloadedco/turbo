<?php

namespace Springloaded\Turbo\Services;

use Illuminate\Filesystem\Filesystem;

class SkillsService
{
    public function __construct(
        protected Filesystem $files,
    ) {}

    /**
     * Get the package root path.
     */
    public function getPackagePath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Get available skill names from the package's .ai/skills directory.
     *
     * @return array<string>
     */
    public function getAvailableSkills(): array
    {
        $skillsPath = $this->getPackagePath().'/.ai/skills';

        if (! $this->files->isDirectory($skillsPath)) {
            return [];
        }

        return collect($this->files->directories($skillsPath))
            ->map(fn ($path) => basename($path))
            ->values()
            ->all();
    }

    /**
     * Get agent choices for skill installation.
     *
     * Returns an associative array of agent-id => label for use in multiselect prompts.
     * The keys match the agent identifiers expected by `npx skills add --agent`.
     *
     * @return array<string, string>
     */
    public function getAgentChoices(): array
    {
        return [
            'claude-code' => 'Claude Code',
            'cursor' => 'Cursor',
            'codex' => 'Codex',
            'github-copilot' => 'GitHub Copilot',
        ];
    }

    /**
     * Get all installed skill paths across supported agent directories.
     *
     * @return array<string>
     */
    public function getInstalledSkillPaths(): array
    {
        $agentPaths = [
            '.agents/skills', // Canonical location used by npx skills for symlinks
            '.claude/skills',
            '.cursor/skills',
            '.codex/skills',
            '.github/skills',
        ];

        $paths = [];

        foreach ($agentPaths as $agentPath) {
            $fullPath = base_path($agentPath);
            if ($this->files->isDirectory($fullPath)) {
                $paths[] = $fullPath;
            }
        }

        return $paths;
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
        $configured = config('turbo.feedback_loops', []);

        return ! empty($configured) ? $configured : $this->getDefaultFeedbackLoops();
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
