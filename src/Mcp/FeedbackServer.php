<?php

declare(strict_types=1);

namespace Springloaded\Turbo\Mcp;

use Laravel\Mcp\Server;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

class FeedbackServer extends Server
{
    protected string $name = 'turbo-feedback';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server provides developer feedback tools for the Turbo AI development toolkit.
        Use the get-developer-feedback tool to retrieve screenshots and annotations captured by the developer from their browser.
    MARKDOWN;

    /**
     * @var array<int, \Laravel\Mcp\Server\Tool|class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetDeveloperFeedback::class,
    ];
}
