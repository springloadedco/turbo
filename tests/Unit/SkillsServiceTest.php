<?php

use Springloaded\Turbo\Services\SkillsService;

it('returns correct package path', function () {
    $service = app(SkillsService::class);

    expect(basename($service->getPackagePath()))->toBe('turbo');
});

it('returns installed skill paths for existing directories', function () {
    $service = app(SkillsService::class);

    // No agent directories exist by default in test
    $paths = $service->getInstalledSkillPaths();

    expect($paths)->toBeArray();
});

it('processes template placeholders', function () {
    $service = app(SkillsService::class);

    $content = 'Run {{ $feedback_loops }} to verify.';
    $processed = $service->processTemplate($content);

    expect($processed)->not->toContain('{{ $feedback_loops }}');
    expect($processed)->toContain('`composer lint`');
});

it('processes checklist template placeholders', function () {
    $service = app(SkillsService::class);

    $content = "## Checklist\n{{ \$feedback_loops_checklist }}";
    $processed = $service->processTemplate($content);

    expect($processed)->not->toContain('{{ $feedback_loops_checklist }}');
    expect($processed)->toContain('- [ ] `composer lint` passes');
});

it('formats commands as inline code list', function () {
    $service = app(SkillsService::class);

    $result = $service->formatInline(['composer lint', 'npm run test']);

    expect($result)->toBe('`composer lint`, `npm run test`');
});

it('formats commands as markdown checklist', function () {
    $service = app(SkillsService::class);

    $result = $service->formatChecklist(['composer lint', 'npm run test']);

    expect($result)->toBe("- [ ] `composer lint` passes\n- [ ] `npm run test` passes");
});

it('returns configured feedback loops', function () {
    $service = app(SkillsService::class);

    $loops = $service->getFeedbackLoops();

    expect($loops)->toBeArray();
    expect($loops)->toContain('composer lint');
});

it('returns default feedback loops', function () {
    $service = app(SkillsService::class);

    $defaults = $service->getDefaultFeedbackLoops();

    expect($defaults)->toBe([
        'composer lint',
        'composer test',
        'composer analyse',
        'npm run lint',
        'npm run types',
        'npm run build',
        'npm run test',
    ]);
});

it('returns available skill names from package', function () {
    $service = app(SkillsService::class);

    $skills = $service->getAvailableSkills();

    expect($skills)->toBeArray();
    expect($skills)->toContain('laravel-controllers');
    expect($skills)->toContain('github-issue');
    expect($skills)->not->toContain('.'); // no dots
});

it('returns agent choices with labels and values', function () {
    $service = app(SkillsService::class);

    $agents = $service->getAgentChoices();

    expect($agents)->toBeArray();
    expect($agents)->toHaveKey('claude-code');
    expect($agents)->toHaveKey('cursor');
    expect($agents)->toHaveKey('codex');
});
