<?php

namespace Springloaded\Turbo\Commands\Concerns;

use Symfony\Component\Process\Process;

trait ProcessesSkills
{
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
        $this->line('Then re-run <comment>turbo:skills</comment> to apply changes.');
        $this->newLine();
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
