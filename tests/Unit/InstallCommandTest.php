<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Springloaded\Turbo\Commands\InstallCommand;
use Springloaded\Turbo\Services\FeedbackLoopDetector;
use Springloaded\Turbo\Services\SkillsService;

beforeEach(function () {
    foreach (['.claude', '.cursor', '.codex'] as $dir) {
        $path = base_path($dir);
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

afterEach(function () {
    foreach (['.claude', '.cursor', '.codex'] as $dir) {
        $path = base_path($dir);
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    $gitignorePath = base_path('.gitignore');
    if (File::exists($gitignorePath)) {
        $contents = File::get($gitignorePath);
        $contents = preg_replace('/\n# Claude local settings \(contains secrets\)\n\.claude\/settings\.local\.json\n/', '', $contents);
        File::put($gitignorePath, $contents);
    }
});

/**
 * Register a testable install command with overridable behavior.
 */
function registerTestableInstallCommand(array $overrides = []): void
{
    $command = new class($overrides) extends InstallCommand
    {
        private array $testOverrides;

        public function __construct(array $overrides)
        {
            $this->testOverrides = $overrides;
            parent::__construct(
                app(SkillsService::class),
                app(\Illuminate\Filesystem\Filesystem::class),
                app(FeedbackLoopDetector::class),
            );
        }

        protected function checkNpxAvailable(): bool
        {
            return $this->testOverrides['npxAvailable'] ?? true;
        }

        protected function runNpxSkillsAdd(string $source, array $skills, array $agents): int
        {
            if (isset($this->testOverrides['runNpxSkillsAdd'])) {
                return ($this->testOverrides['runNpxSkillsAdd'])($source, $skills, $agents);
            }

            return $this->testOverrides['npxExitCode'] ?? 0;
        }
    };

    app(Kernel::class)->registerCommand($command);
}

it('fails when npx is not available', function () {
    registerTestableInstallCommand(['npxAvailable' => false]);

    $this->artisan('turbo:install', ['--no-interaction' => true])
        ->expectsOutput('npx is required to install skills. Please install Node.js and npm first.')
        ->assertFailed();
});

it('installs all skills to all agents in non-interactive mode', function () {
    $capturedCalls = [];

    registerTestableInstallCommand([
        'runNpxSkillsAdd' => function ($source, $skills, $agents) use (&$capturedCalls) {
            $capturedCalls[] = compact('source', 'skills', 'agents');

            return 0;
        },
    ]);

    $this->artisan('turbo:install', ['--no-interaction' => true])
        ->assertSuccessful();

    // Should have two calls: one for turbo skills, one for agent-browser
    expect($capturedCalls)->toHaveCount(2);

    // First call: turbo package skills
    expect($capturedCalls[0]['source'])->toEndWith('turbo');
    expect($capturedCalls[0]['skills'])->toContain('laravel-controllers');
    expect($capturedCalls[0]['skills'])->toContain('github-issue');
    expect($capturedCalls[0]['agents'])->toBe(['claude-code', 'cursor', 'codex']);

    // Second call: agent-browser
    expect($capturedCalls[1]['source'])->toBe('vercel-labs/agent-browser');
    expect($capturedCalls[1]['skills'])->toBe(['agent-browser']);
    expect($capturedCalls[1]['agents'])->toBe(['claude-code', 'cursor', 'codex']);
});

it('processes templates after installing turbo skills', function () {
    registerTestableInstallCommand([
        'runNpxSkillsAdd' => function ($source) {
            // Only simulate file creation for turbo source (not third-party)
            if (str_ends_with($source, 'turbo')) {
                $skillsPath = base_path('.claude/skills/github-issue');
                File::makeDirectory($skillsPath, 0755, true);
                File::copyDirectory(
                    dirname(__DIR__, 2).'/.ai/skills/github-issue',
                    $skillsPath
                );
            }

            return 0;
        },
    ]);

    $this->artisan('turbo:install', ['--no-interaction' => true])
        ->assertSuccessful();

    $content = File::get(base_path('.claude/skills/github-issue/SKILL.md'));
    expect($content)->not->toContain('{{ $feedback_loops }}');
    expect($content)->toContain('`composer lint`');
});

it('fails when turbo skill installation fails', function () {
    registerTestableInstallCommand(['npxExitCode' => 1]);

    $this->artisan('turbo:install', ['--no-interaction' => true])
        ->expectsOutput('Failed to install Turbo skills.')
        ->assertFailed();
});

it('fails when third-party skill installation fails', function () {
    registerTestableInstallCommand([
        'runNpxSkillsAdd' => function ($source) {
            // Turbo skills succeed, third-party fails
            return str_ends_with($source, 'turbo') ? 0 : 1;
        },
    ]);

    $this->artisan('turbo:install', ['--no-interaction' => true])
        ->expectsOutput('Failed to install agent-browser.')
        ->assertFailed();
});
