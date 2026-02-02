<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Springloaded\Turbo\Services\SkillsService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class PublishSkillsCommand extends Command
{
    public $signature = 'turbo:publish
        {--all : Publish all skills without prompting}
        {--force : Overwrite existing skills without prompting}
        {--skills=* : Specific skills to publish}';

    public $description = 'Publish AI skills from Turbo to your project';

    public function __construct(
        protected SkillsService $skills,
        protected Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $availableSkills = $this->skills->discover();

        if ($availableSkills->isEmpty()) {
            $this->error('No skills found in package.');

            return self::FAILURE;
        }

        $skillsToPublish = $this->resolveSkillsToPublish($availableSkills);

        if (empty($skillsToPublish)) {
            $this->info('No skills selected for publishing.');

            return self::SUCCESS;
        }

        $skillsToPublish = $this->resolveConflicts($skillsToPublish);

        if (empty($skillsToPublish)) {
            $this->info('No skills to publish after conflict resolution.');

            return self::SUCCESS;
        }

        $this->publishSkills($skillsToPublish);
        $this->configureGitHubToken();

        $this->newLine();
        $this->info('Published skills:');
        foreach ($skillsToPublish as $skill) {
            $this->line("  - {$skill['name']}");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve which skills to publish based on options or prompts.
     */
    protected function resolveSkillsToPublish(Collection $available): array
    {
        // Handle --skills option
        $specificSkills = $this->option('skills');
        if (! empty($specificSkills)) {
            return $this->skills->findMany($specificSkills)->all();
        }

        // Handle --all option
        if ($this->option('all')) {
            return $available->all();
        }

        // Interactive selection
        $options = $available->mapWithKeys(function ($skill) {
            return [$skill['name'] => "{$skill['name']}: {$skill['description']}"];
        })->all();

        $selected = multiselect(
            label: 'Which skills would you like to publish?',
            options: $options,
            default: array_keys($options),
        );

        return $this->skills->findMany($selected)->all();
    }

    /**
     * Check for existing skills and resolve conflicts.
     */
    protected function resolveConflicts(array $skills): array
    {
        if ($this->option('force')) {
            return $skills;
        }

        return collect($skills)->filter(function ($skill) {
            if (! $this->skills->existsInTarget($skill['name'])) {
                return true;
            }

            return confirm(
                label: "Skill '{$skill['name']}' already exists. Overwrite?",
                default: false,
            );
        })->values()->all();
    }

    /**
     * Publish selected skills to the target directory.
     */
    protected function publishSkills(array $skills): void
    {
        $targetPath = $this->skills->getTargetSkillsPath();

        if (! $this->files->isDirectory($targetPath)) {
            $this->files->makeDirectory($targetPath, 0755, true);
        }

        foreach ($skills as $skill) {
            $destination = $targetPath.'/'.$skill['name'];

            if ($this->files->isDirectory($destination)) {
                $this->files->deleteDirectory($destination);
            }

            $this->files->copyDirectory($skill['path'], $destination);
            $this->info("Published skill: {$skill['name']}");
        }
    }

    /**
     * Prompt for GitHub token and create settings.local.json if provided.
     */
    protected function configureGitHubToken(): void
    {
        // Skip in non-interactive mode
        if (! $this->input->isInteractive()) {
            return;
        }

        $settingsPath = base_path('.claude/settings.local.json');

        // Skip if settings.local.json already exists
        if ($this->files->exists($settingsPath)) {
            return;
        }

        $wantsToken = confirm(
            label: 'Do you have a GitHub token to configure? (enables gh CLI access for Claude)',
            default: false,
        );

        if (! $wantsToken) {
            return;
        }

        $token = text(
            label: 'Enter your GitHub token',
            required: true,
        );

        if (empty($token)) {
            return;
        }

        // Ensure .claude directory exists
        $claudeDir = base_path('.claude');
        if (! $this->files->isDirectory($claudeDir)) {
            $this->files->makeDirectory($claudeDir, 0755, true);
        }

        // Create settings.local.json with the token
        $settings = [
            'env' => [
                'GITHUB_TOKEN' => $token,
            ],
        ];

        $this->files->put(
            $settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info('Created .claude/settings.local.json with GitHub token');

        // Add to .gitignore if needed
        $this->addToGitignore();
    }

    /**
     * Add settings.local.json to .gitignore if not already present.
     */
    protected function addToGitignore(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! $this->files->exists($gitignorePath)) {
            return;
        }

        $contents = $this->files->get($gitignorePath);
        $pattern = '.claude/settings.local.json';

        // Check if already in gitignore
        if (str_contains($contents, $pattern)) {
            return;
        }

        // Append to gitignore
        $addition = "\n# Claude local settings (contains secrets)\n{$pattern}\n";
        $this->files->append($gitignorePath, $addition);

        $this->info('Added .claude/settings.local.json to .gitignore');
    }
}
