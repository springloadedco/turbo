<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Springloaded\Turbo\Commands\PublishSkillsCommand;
use Springloaded\Turbo\Services\SkillsService;

beforeEach(function () {
    // Clean up any test artifacts
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

    // Restore .gitignore if modified
    $gitignorePath = base_path('.gitignore');
    if (File::exists($gitignorePath)) {
        $contents = File::get($gitignorePath);
        $contents = preg_replace('/\n# Claude local settings \(contains secrets\)\n\.claude\/settings\.local\.json\n/', '', $contents);
        File::put($gitignorePath, $contents);
    }
});

/**
 * Register a testable command with overridable npx behavior.
 */
function registerTestableCommand(array $overrides = []): void
{
    $command = new class($overrides) extends PublishSkillsCommand
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

/**
 * Call the protected addToGitignore method on a PublishSkillsCommand instance.
 */
function callAddToGitignore(): void
{
    $command = new PublishSkillsCommand(
        app(SkillsService::class),
        app(\Illuminate\Filesystem\Filesystem::class)
    );

    $reflection = new ReflectionMethod(PublishSkillsCommand::class, 'addToGitignore');
    $reflection->invoke($command);
}

it('fails when npx is not available', function () {
    registerTestableCommand(['npxAvailable' => false]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->expectsOutput('npx is required to install skills. Please install Node.js and npm first.')
        ->assertFailed();
});

it('fails when npx skills add returns non-zero exit code', function () {
    registerTestableCommand(['npxExitCode' => 1]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->expectsOutput('Failed to install skills via npx skills.')
        ->assertFailed();
});

it('passes package path to npx skills add', function () {
    $capturedPath = null;

    registerTestableCommand([
        'runNpxSkillsAdd' => function ($packagePath) use (&$capturedPath) {
            $capturedPath = $packagePath;

            return 0;
        },
    ]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful();

    expect($capturedPath)->toEndWith('turbo');
});

it('processes templates after npx skills installs', function () {
    registerTestableCommand([
        'runNpxSkillsAdd' => function () {
            // Simulate npx skills installing a skill with template placeholders
            $skillsPath = base_path('.claude/skills/github-issue');
            File::makeDirectory($skillsPath, 0755, true);
            File::copyDirectory(
                dirname(__DIR__, 2).'/.ai/skills/github-issue',
                $skillsPath
            );

            return 0;
        },
    ]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful();

    $processedContent = File::get(base_path('.claude/skills/github-issue/SKILL.md'));
    expect($processedContent)->not->toContain('{{ $feedback_loops }}');
    expect($processedContent)->toContain('`composer lint`');
});

it('processes templates across multiple agent directories', function () {
    registerTestableCommand([
        'runNpxSkillsAdd' => function () {
            // Simulate npx skills installing to both .claude and .cursor
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

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful();

    // Both locations should have processed templates
    foreach (['.claude/skills/github-issue', '.cursor/skills/github-issue'] as $path) {
        $content = File::get(base_path($path.'/SKILL.md'));
        expect($content)->not->toContain('{{ $feedback_loops }}');
        expect($content)->toContain('`composer lint`');
    }
});

it('skips symlinked skill directories during template processing', function () {
    registerTestableCommand([
        'runNpxSkillsAdd' => function () {
            // Create canonical skill with template placeholders
            $sourcePath = base_path('.claude/skills/github-issue');
            File::makeDirectory($sourcePath, 0755, true);
            File::copyDirectory(
                dirname(__DIR__, 2).'/.ai/skills/github-issue',
                $sourcePath
            );

            // Create a symlinked skill directory in .cursor (how npx skills works)
            $cursorPath = base_path('.cursor/skills');
            File::makeDirectory($cursorPath, 0755, true);
            symlink($sourcePath, $cursorPath.'/github-issue');

            return 0;
        },
    ]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful();

    // The real file should be processed
    $content = File::get(base_path('.claude/skills/github-issue/SKILL.md'));
    expect($content)->not->toContain('{{ $feedback_loops }}');

    // The symlinked directory should have been skipped (not processed independently)
    expect(is_link(base_path('.cursor/skills/github-issue')))->toBeTrue();
})->skip(PHP_OS_FAMILY === 'Windows', 'Symlinks require special permissions on Windows');

it('outputs intro message before running npx skills', function () {
    registerTestableCommand(['npxExitCode' => 0]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->expectsOutput('Installing skills via npx skills (https://skills.sh)...')
        ->assertSuccessful();
});

it('skips github token prompt if settings.local.json already exists', function () {
    $claudeDir = base_path('.claude');
    $settingsPath = $claudeDir.'/settings.local.json';

    File::makeDirectory($claudeDir, 0755, true);
    File::put($settingsPath, json_encode(['existing' => true]));

    registerTestableCommand(['npxExitCode' => 0]);

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful()
        ->doesntExpectOutput('Created '.$settingsPath.' with GitHub token');
});

it('skips github token prompt in non-interactive mode', function () {
    registerTestableCommand(['npxExitCode' => 0]);

    $settingsPath = base_path('.claude/settings.local.json');

    $this->artisan('turbo:publish', ['--no-interaction' => true])
        ->assertSuccessful()
        ->doesntExpectOutput('Created '.$settingsPath.' with GitHub token');

    expect(File::exists($settingsPath))->toBeFalse();
});

it('adds settings.local.json to gitignore', function () {
    $gitignorePath = base_path('.gitignore');
    File::put($gitignorePath, "/vendor\n");

    callAddToGitignore();

    $contents = File::get($gitignorePath);
    expect($contents)->toContain('.claude/settings.local.json');
});

it('does not duplicate gitignore entry if already present', function () {
    $gitignorePath = base_path('.gitignore');
    File::put($gitignorePath, "/vendor\n.claude/settings.local.json\n");

    callAddToGitignore();

    $contents = File::get($gitignorePath);
    $count = substr_count($contents, '.claude/settings.local.json');
    expect($count)->toBe(1);
});
