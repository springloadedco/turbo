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
        }
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
