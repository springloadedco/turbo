<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Springloaded\Turbo\Services\SkillsService;
use Symfony\Component\Process\Process;

class SkillsCommand extends Command
{
    public $signature = 'turbo:skills';

    public $description = 'Publish Turbo skills to your project';

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

        $this->info('Publishing Turbo skills via npx skills (https://skills.sh)...');
        $this->line('Source: '.$packagePath);
        $this->newLine();

        $exitCode = $this->runNpxSkillsAdd($packagePath);

        if ($exitCode !== 0) {
            $this->error('Failed to publish skills.');

            return self::FAILURE;
        }

        $this->processInstalledSkills();

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
     * Run npx skills add interactively.
     *
     * Uses --skill '*' to pre-select all Turbo skills, letting the user
     * choose agents via the npx interactive prompt.
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
     * Process templates in all installed skill locations.
     */
    protected function processInstalledSkills(): void
    {
        $paths = $this->skills->getInstalledSkillPaths();

        $processed = false;

        foreach ($paths as $agentPath) {
            foreach ($this->files->directories($agentPath) as $skillDir) {
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
}
