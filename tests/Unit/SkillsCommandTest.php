<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Springloaded\Turbo\Commands\SkillsCommand;
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
});

/**
 * Register a testable skills command with overridable npx behavior.
 */
function registerTestableSkillsCommand(array $overrides = []): void
{
    $command = new class($overrides) extends SkillsCommand
    {
        private array $testOverrides;

        public function __construct(array $overrides)
        {
            $this->testOverrides = $overrides;
            parent::__construct(
                app(SkillsService::class),
                app(\Illuminate\Filesystem\Filesystem::class)
            );
        }

        protected function checkNpxAvailable(): bool
        {
            return $this->testOverrides['npxAvailable'] ?? true;
        }

        protected function runNpxSkillsAdd(string $packagePath): int
        {
            if (isset($this->testOverrides['runNpxSkillsAdd'])) {
                return ($this->testOverrides['runNpxSkillsAdd'])($packagePath);
            }

            return $this->testOverrides['npxExitCode'] ?? 0;
        }
    };

    app(Kernel::class)->registerCommand($command);
}

it('fails when npx is not available', function () {
    registerTestableSkillsCommand(['npxAvailable' => false]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->expectsOutput('npx is required to install skills. Please install Node.js and npm first.')
        ->assertFailed();
});

it('fails when npx skills add returns non-zero exit code', function () {
    registerTestableSkillsCommand(['npxExitCode' => 1]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->expectsOutput('Failed to publish skills.')
        ->assertFailed();
});

it('passes package path to npx skills add', function () {
    $capturedPath = null;

    registerTestableSkillsCommand([
        'runNpxSkillsAdd' => function ($packagePath) use (&$capturedPath) {
            $capturedPath = $packagePath;

            return 0;
        },
    ]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->assertSuccessful();

    expect($capturedPath)->toEndWith('.ai/skills');
});

it('processes templates after npx skills installs', function () {
    registerTestableSkillsCommand([
        'runNpxSkillsAdd' => function () {
            $skillsPath = base_path('.claude/skills/github-issue');
            File::makeDirectory($skillsPath, 0755, true);
            File::copyDirectory(
                dirname(__DIR__, 2).'/.ai/skills/github-issue',
                $skillsPath
            );

            return 0;
        },
    ]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->assertSuccessful();

    $processedContent = File::get(base_path('.claude/skills/github-issue/SKILL.md'));
    expect($processedContent)->not->toContain('{{ $feedback_loops }}');
    expect($processedContent)->toContain('`composer lint`');
});

it('processes templates across multiple agent directories', function () {
    registerTestableSkillsCommand([
        'runNpxSkillsAdd' => function () {
            foreach (['.claude/skills/github-issue', '.cursor/skills/github-issue'] as $path) {
                $skillsPath = base_path($path);
                File::makeDirectory($skillsPath, 0755, true);
                File::copyDirectory(
                    dirname(__DIR__, 2).'/.ai/skills/github-issue',
                    $skillsPath
                );
            }

            return 0;
        },
    ]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->assertSuccessful();

    foreach (['.claude/skills/github-issue', '.cursor/skills/github-issue'] as $path) {
        $content = File::get(base_path($path.'/SKILL.md'));
        expect($content)->not->toContain('{{ $feedback_loops }}');
        expect($content)->toContain('`composer lint`');
    }
});

it('skips symlinked skill directories during template processing', function () {
    registerTestableSkillsCommand([
        'runNpxSkillsAdd' => function () {
            $sourcePath = base_path('.claude/skills/github-issue');
            File::makeDirectory($sourcePath, 0755, true);
            File::copyDirectory(
                dirname(__DIR__, 2).'/.ai/skills/github-issue',
                $sourcePath
            );

            $cursorPath = base_path('.cursor/skills');
            File::makeDirectory($cursorPath, 0755, true);
            symlink($sourcePath, $cursorPath.'/github-issue');

            return 0;
        },
    ]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->assertSuccessful();

    $content = File::get(base_path('.claude/skills/github-issue/SKILL.md'));
    expect($content)->not->toContain('{{ $feedback_loops }}');

    expect(is_link(base_path('.cursor/skills/github-issue')))->toBeTrue();
})->skip(PHP_OS_FAMILY === 'Windows', 'Symlinks require special permissions on Windows');

it('outputs intro message before running npx skills', function () {
    registerTestableSkillsCommand(['npxExitCode' => 0]);

    $this->artisan('turbo:skills', ['--no-interaction' => true])
        ->expectsOutput('Publishing Turbo skills via npx skills (https://skills.sh)...')
        ->assertSuccessful();
});
