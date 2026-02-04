<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Springloaded\Turbo\Services\SkillsService;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class PublishSkillsCommand extends Command
{
    public $signature = 'turbo:publish';

    public $description = 'Publish AI skills from Turbo to your project';

    public function __construct(
        protected SkillsService $skills,
        protected Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->checkNpxAvailable()) {
            $this->error('npx is required to install skills. Please install Node.js and npm first.');
            $this->line('See: https://nodejs.org/');

            return self::FAILURE;
        }

        $packagePath = $this->skills->getPackagePath();

        if (! $this->files->isDirectory($packagePath.'/.ai/skills')) {
            $this->error('No skills found in package.');

            return self::FAILURE;
        }

        $this->info('Installing skills via npx skills (https://skills.sh)...');
        $this->line('Source: '.$packagePath);
        $this->newLine();

        // Run npx skills add interactively
        $exitCode = $this->runNpxSkillsAdd($packagePath);

        if ($exitCode !== 0) {
            $this->error('Failed to install skills via npx skills.');

            return self::FAILURE;
        }

        // Install agent-browser skill
        $exitCode = $this->runNpxSkillsAddAgentBrowser();

        if ($exitCode !== 0) {
            $this->error('Failed to install agent-browser skill.');

            return self::FAILURE;
        }

        // Process templates in installed locations
        $this->processInstalledSkills();

        // Configure GitHub token
        $this->configureGitHubToken();

        return self::SUCCESS;
    }

    /**
     * Check if npx is available on the system.
     */
    protected function checkNpxAvailable(): bool
    {
        $process = Process::fromShellCommandline('npx --version');
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Run npx skills add interactively with TTY passthrough.
     *
     * Pre-selects all skills with --skill '*', then lets the user
     * choose which agents to install to via the interactive prompt.
     */
    protected function runNpxSkillsAdd(string $packagePath): int
    {
        $process = new Process(
            ['npx', 'skills', 'add', $packagePath, '--skill', '*'],
            base_path()
        );
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->run();

        return $process->getExitCode();
    }

    /**
     * Install the agent-browser skill from vercel-labs.
     */
    protected function runNpxSkillsAddAgentBrowser(): int
    {
        $this->newLine();
        $this->info('Installing agent-browser skill...');
        $this->newLine();

        $process = new Process(
            ['npx', 'skills', 'add', 'https://github.com/vercel-labs/agent-browser', '--skill', 'agent-browser'],
            base_path()
        );
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->run();

        return $process->getExitCode();
    }

    /**
     * Process templates in all installed skill locations.
     */
    protected function processInstalledSkills(): void
    {
        $paths = $this->skills->getInstalledSkillPaths();

        $processed = false;

        foreach ($paths as $agentPath) {
            foreach ($this->files->directories($agentPath) as $skillDir) {
                // Skip symlinked skill directories to avoid modifying the canonical copy twice
                if (is_link($skillDir)) {
                    continue;
                }

                $this->processSkillTemplates($skillDir);
                $processed = true;
            }
        }

        if ($processed) {
            $this->info('Processed skill templates with project configuration.');
            $this->displayFeedbackLoopConfig();
        }
    }

    /**
     * Display the current feedback loop configuration.
     */
    protected function displayFeedbackLoopConfig(): void
    {
        $feedbackLoops = $this->skills->getFeedbackLoops();

        $this->newLine();
        $this->line('Feedback loops injected into skill templates:');

        foreach ($feedbackLoops as $command) {
            $this->line('  - '.$command);
        }

        $this->newLine();
        $this->line('To customize, publish the config and edit the <comment>feedback_loops</comment> array:');
        $this->line('  <comment>php artisan vendor:publish --tag=turbo-config</comment>');
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

        $this->info('Created '.$settingsPath.' with GitHub token');

        // Add to .gitignore if needed
        $this->addToGitignore();
    }

    /**
     * Process template placeholders in skill markdown files.
     */
    protected function processSkillTemplates(string $skillPath): void
    {
        $mdFiles = $this->files->glob($skillPath.'/*.md');

        foreach ($mdFiles as $file) {
            $content = $this->files->get($file);
            $processed = $this->skills->processTemplate($content);

            if ($processed !== $content) {
                $this->files->put($file, $processed);
            }
        }
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

        $this->info('Added .claude/settings.local.json to '.$gitignorePath);
    }
}
