<?php

use Springloaded\Turbo\Services\SkillsService;

it('discovers skills from package directory', function () {
    $service = app(SkillsService::class);

    $skills = $service->discover();

    expect($skills)->toHaveCount(5);
    expect($skills->pluck('name')->toArray())->toContain(
        'laravel-actions',
        'laravel-controllers',
        'laravel-inertia',
        'laravel-testing',
        'laravel-validation'
    );
});

it('returns skill with name description and path', function () {
    $service = app(SkillsService::class);

    $skills = $service->discover();
    $skill = $skills->firstWhere('name', 'laravel-actions');

    expect($skill)->toHaveKeys(['name', 'description', 'path']);
    expect($skill['name'])->toBe('laravel-actions');
    expect($skill['description'])->toContain('Action class patterns');
    expect($skill['path'])->toEndWith('/laravel-actions');
});

it('finds a specific skill by name', function () {
    $service = app(SkillsService::class);

    $skill = $service->find('laravel-actions');

    expect($skill)->not->toBeNull();
    expect($skill['name'])->toBe('laravel-actions');
});

it('returns null for non-existent skill', function () {
    $service = app(SkillsService::class);

    $skill = $service->find('non-existent-skill');

    expect($skill)->toBeNull();
});

it('finds multiple skills by name', function () {
    $service = app(SkillsService::class);

    $skills = $service->findMany(['laravel-actions', 'laravel-testing']);

    expect($skills)->toHaveCount(2);
    expect($skills->pluck('name')->toArray())->toBe(['laravel-actions', 'laravel-testing']);
});

it('parses yaml frontmatter correctly', function () {
    $service = app(SkillsService::class);

    $content = <<<'MD'
---
name: test-skill
description: A test skill for testing
allowed-tools: Read, Write
---

# Test Skill

Content here.
MD;

    $result = $service->parseFrontmatter($content);

    expect($result)->toBe([
        'name' => 'test-skill',
        'description' => 'A test skill for testing',
        'allowed-tools' => 'Read, Write',
    ]);
});

it('returns empty array for content without frontmatter', function () {
    $service = app(SkillsService::class);

    $content = '# No frontmatter here';

    $result = $service->parseFrontmatter($content);

    expect($result)->toBe([]);
});

it('checks if skill exists in target', function () {
    $service = app(SkillsService::class);

    // Before publishing, skill should not exist
    expect($service->existsInTarget('laravel-actions'))->toBeFalse();
});

it('returns correct package skills path', function () {
    $service = app(SkillsService::class);

    expect($service->getPackageSkillsPath())->toEndWith('/.ai/skills');
});

it('returns correct target skills path', function () {
    $service = app(SkillsService::class);

    expect($service->getTargetSkillsPath())->toBe(base_path('.ai/skills'));
});
