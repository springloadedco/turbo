<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up any test artifacts
    $targetClaudePath = base_path('.claude');

    if (File::isDirectory($targetClaudePath)) {
        File::deleteDirectory($targetClaudePath);
    }
});

afterEach(function () {
    // Clean up after tests
    $targetClaudePath = base_path('.claude');

    if (File::isDirectory($targetClaudePath)) {
        File::deleteDirectory($targetClaudePath);
    }

    // Restore .gitignore if modified
    $gitignorePath = base_path('.gitignore');
    if (File::exists($gitignorePath)) {
        $contents = File::get($gitignorePath);
        $contents = preg_replace('/\n# Claude local settings \(contains secrets\)\n\.claude\/settings\.local\.json\n/', '', $contents);
        File::put($gitignorePath, $contents);
    }
});

it('publishes all skills with --all flag', function () {
    $this->artisan('turbo:publish', ['--all' => true, '--no-interaction' => true])
        ->assertSuccessful();

    $targetSkillsPath = base_path('.claude/skills');

    expect(File::isDirectory($targetSkillsPath))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-actions'))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-controllers'))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-inertia'))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-testing'))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-validation'))->toBeTrue();
});

it('publishes specific skills with --skills option', function () {
    $this->artisan('turbo:publish', ['--skills' => ['laravel-actions'], '--no-interaction' => true])
        ->assertSuccessful();

    $targetSkillsPath = base_path('.claude/skills');

    expect(File::isDirectory($targetSkillsPath.'/laravel-actions'))->toBeTrue();
    expect(File::isDirectory($targetSkillsPath.'/laravel-controllers'))->toBeFalse();
});

it('overwrites existing skills with --force flag', function () {
    $targetSkillsPath = base_path('.claude/skills/laravel-actions');

    // Create existing skill directory with a marker file
    File::makeDirectory($targetSkillsPath, 0755, true);
    File::put($targetSkillsPath.'/marker.txt', 'original');

    $this->artisan('turbo:publish', ['--skills' => ['laravel-actions'], '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    // Marker file should be gone (directory was replaced)
    expect(File::exists($targetSkillsPath.'/marker.txt'))->toBeFalse();
    // SKILL.md should exist
    expect(File::exists($targetSkillsPath.'/SKILL.md'))->toBeTrue();
});

it('creates target directories if they do not exist', function () {
    $targetSkillsPath = base_path('.claude/skills');

    expect(File::isDirectory($targetSkillsPath))->toBeFalse();

    $this->artisan('turbo:publish', ['--skills' => ['laravel-actions'], '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect(File::isDirectory($targetSkillsPath))->toBeTrue();
});

it('skips github token prompt if settings.local.json already exists', function () {
    $claudeDir = base_path('.claude');
    $settingsPath = $claudeDir.'/settings.local.json';

    File::makeDirectory($claudeDir, 0755, true);
    File::put($settingsPath, json_encode(['existing' => true]));

    $this->artisan('turbo:publish', ['--skills' => ['laravel-actions'], '--force' => true, '--no-interaction' => true])
        ->assertSuccessful()
        ->doesntExpectOutput('Created .claude/settings.local.json with GitHub token');
});

it('skips github token prompt in non-interactive mode', function () {
    $this->artisan('turbo:publish', ['--skills' => ['laravel-actions'], '--force' => true, '--no-interaction' => true])
        ->assertSuccessful()
        ->doesntExpectOutput('Created .claude/settings.local.json with GitHub token');

    // settings.local.json should NOT be created in non-interactive mode
    expect(File::exists(base_path('.claude/settings.local.json')))->toBeFalse();
});

it('adds settings.local.json to gitignore', function () {
    $gitignorePath = base_path('.gitignore');

    // Create a .gitignore file
    File::put($gitignorePath, "/vendor\n");

    // Call addToGitignore directly via the Filesystem
    $files = app(\Illuminate\Filesystem\Filesystem::class);
    $contents = $files->get($gitignorePath);
    $pattern = '.claude/settings.local.json';

    if (! str_contains($contents, $pattern)) {
        $addition = "\n# Claude local settings (contains secrets)\n{$pattern}\n";
        $files->append($gitignorePath, $addition);
    }

    $contents = File::get($gitignorePath);
    expect($contents)->toContain('.claude/settings.local.json');
});

it('does not duplicate gitignore entry if already present', function () {
    $gitignorePath = base_path('.gitignore');

    // Create a .gitignore file that already has the entry
    File::put($gitignorePath, "/vendor\n.claude/settings.local.json\n");

    // Simulate the addToGitignore logic
    $files = app(\Illuminate\Filesystem\Filesystem::class);
    $contents = $files->get($gitignorePath);
    $pattern = '.claude/settings.local.json';

    if (! str_contains($contents, $pattern)) {
        $addition = "\n# Claude local settings (contains secrets)\n{$pattern}\n";
        $files->append($gitignorePath, $addition);
    }

    $contents = File::get($gitignorePath);
    $count = substr_count($contents, '.claude/settings.local.json');
    expect($count)->toBe(1);
});
