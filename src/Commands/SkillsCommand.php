<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Springloaded\Turbo\Commands\Concerns\ProcessesSkills;
use Springloaded\Turbo\Services\SkillsService;
use Symfony\Component\Process\Process;

class SkillsCommand extends Command
{
    use ProcessesSkills;

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

        $skillsPath = $this->skills->getSkillsSourcePath();

        if (! $this->files->isDirectory($skillsPath)) {
            $this->error('No skills found in package.');

            return self::FAILURE;
        }

        $this->info('Publishing Turbo skills via npx skills (https://skills.sh)...');
        $this->line('Source: '.$skillsPath);
        $this->newLine();

        $exitCode = $this->runNpxSkillsAdd($skillsPath);

        if ($exitCode !== 0) {
            $this->error('Failed to publish skills.');

            return self::FAILURE;
        }

        $this->processInstalledSkills();

        return self::SUCCESS;
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
        $process->run();

        return $process->getExitCode();
    }
}
