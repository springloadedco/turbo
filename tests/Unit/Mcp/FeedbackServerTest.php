<?php

use Springloaded\Turbo\Mcp\FeedbackServer;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

it('is a valid mcp server', function () {
    expect(FeedbackServer::class)->toExtend(Laravel\Mcp\Server::class);
});

it('registers the get developer feedback tool', function () {
    $server = new ReflectionClass(FeedbackServer::class);
    $toolsProperty = $server->getProperty('tools');

    expect($toolsProperty->getDefaultValue())->toBe([
        GetDeveloperFeedback::class,
    ]);
});

it('has correct server metadata', function () {
    $server = new ReflectionClass(FeedbackServer::class);

    $nameProperty = $server->getProperty('name');
    expect($nameProperty->getDefaultValue())->toBe('turbo-feedback');

    $versionProperty = $server->getProperty('version');
    expect($versionProperty->getDefaultValue())->toBe('1.0.0');
});
