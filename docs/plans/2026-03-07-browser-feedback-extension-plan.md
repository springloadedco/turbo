# Browser Feedback Extension Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable developers to capture browser screenshots and share them with Claude running inside a Docker sandbox, via a Chrome extension + Native Messaging Host + Laravel MCP server.

**Architecture:** A Chrome extension captures the visible tab and sends the image to a Node.js native messaging host, which writes it to the project workspace. A Laravel MCP server exposes a `get_developer_feedback` tool that reads the images and returns them to Claude. A skill teaches Claude to call the tool when the developer pastes a `[feedback]` trigger string.

**Tech Stack:** Chrome Extension (Manifest V3, JavaScript), Node.js (Native Messaging Host), PHP 8.4 / Laravel MCP SDK (MCP Server), Pest (Tests)

**Design Doc:** `docs/plans/2026-03-07-browser-feedback-extension-design.md`

---

## Task 1: Add Laravel MCP SDK Dependency

**Files:**
- Modify: `composer.json`

**Step 1: Require the Laravel MCP SDK**

Run:
```bash
composer require laravel/mcp
```

**Step 2: Verify installation**

Run:
```bash
composer show laravel/mcp
```

Expected: Package info displayed.

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add laravel/mcp dependency"
```

---

## Task 2: Create the MCP Server Class

**Files:**
- Create: `src/Mcp/FeedbackServer.php`

**Step 1: Write the test**

Create `tests/Unit/Mcp/FeedbackServerTest.php`:

```php
<?php

use Springloaded\Turbo\Mcp\FeedbackServer;

it('is instantiable', function () {
    expect(class_exists(FeedbackServer::class))->toBeTrue();
});

it('registers the GetDeveloperFeedback tool', function () {
    $server = new FeedbackServer;
    $tools = (new ReflectionProperty($server, 'tools'))->getValue($server);

    expect($tools)->toContain(\Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback::class);
});
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter=FeedbackServer`
Expected: FAIL — class not found.

**Step 3: Create the server class**

Create `src/Mcp/FeedbackServer.php`:

```php
<?php

namespace Springloaded\Turbo\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

#[Name('Turbo Feedback')]
#[Version('1.0.0')]
#[Instructions('Provides developer feedback screenshots captured from the browser.')]
class FeedbackServer extends Server
{
    protected array $tools = [
        GetDeveloperFeedback::class,
    ];
}
```

**Step 4: Run test to verify it passes**

Run: `composer test -- --filter=FeedbackServer`
Expected: First test passes, second may fail (tool class doesn't exist yet — that's fine, we build it in Task 3).

**Step 5: Commit**

```bash
git add src/Mcp/FeedbackServer.php tests/Unit/Mcp/FeedbackServerTest.php
git commit -m "chore: add MCP FeedbackServer class"
```

---

## Task 3: Create the GetDeveloperFeedback Tool

**Files:**
- Create: `src/Mcp/Tools/GetDeveloperFeedback.php`
- Create: `tests/Unit/Mcp/Tools/GetDeveloperFeedbackTest.php`

**Step 1: Write the test**

Create `tests/Unit/Mcp/Tools/GetDeveloperFeedbackTest.php`:

```php
<?php

use Illuminate\Support\Facades\Storage;
use Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback;

beforeEach(function () {
    Storage::fake('local');
});

it('returns empty response when no feedback images exist', function () {
    $tool = app(GetDeveloperFeedback::class);
    $request = new \Laravel\Mcp\Request([]);
    $result = $tool->handle($request);

    expect($result)->toBeInstanceOf(\Laravel\Mcp\Response::class);
});

it('returns images sorted chronologically with metadata', function () {
    $feedbackDir = 'agent-captures/feedback';

    // Create two feedback images with manifests
    Storage::put("{$feedbackDir}/2026-03-07-140000-feedback.png", 'fake-png-data-1');
    Storage::put("{$feedbackDir}/2026-03-07-140000-feedback.png.json", json_encode([
        'image' => '2026-03-07-140000-feedback.png',
        'url' => 'https://myapp.test/page-one',
        'annotation' => 'first issue',
        'timestamp' => '2026-03-07T14:00:00Z',
        'viewport' => ['width' => 1440, 'height' => 900],
    ]));

    Storage::put("{$feedbackDir}/2026-03-07-140500-feedback.png", 'fake-png-data-2');
    Storage::put("{$feedbackDir}/2026-03-07-140500-feedback.png.json", json_encode([
        'image' => '2026-03-07-140500-feedback.png',
        'url' => 'https://myapp.test/page-two',
        'annotation' => 'second issue',
        'timestamp' => '2026-03-07T14:05:00Z',
        'viewport' => ['width' => 1440, 'height' => 900],
    ]));

    $tool = app(GetDeveloperFeedback::class);
    $request = new \Laravel\Mcp\Request([]);
    $result = $tool->handle($request);

    // Result should be an array with images and text metadata
    expect($result)->toBeArray();
    expect($result)->toHaveCount(4); // 2 images + 2 text metadata blocks
});
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter=GetDeveloperFeedback`
Expected: FAIL — class not found.

**Step 3: Implement the tool**

Create `src/Mcp/Tools/GetDeveloperFeedback.php`:

```php
<?php

namespace Springloaded\Turbo\Mcp\Tools;

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retrieves developer feedback screenshots captured from the browser. Returns all images in the feedback directory sorted chronologically (oldest first, most recent last), with metadata including URL, annotation, and viewport size.')]
class GetDeveloperFeedback extends Tool
{
    public function handle(Request $request): Response|array
    {
        $feedbackDir = 'agent-captures/feedback';

        if (! Storage::exists($feedbackDir)) {
            return Response::text('No developer feedback found.');
        }

        $files = collect(Storage::files($feedbackDir))
            ->filter(fn (string $file) => str_ends_with($file, '.png'))
            ->sort()
            ->values();

        if ($files->isEmpty()) {
            return Response::text('No developer feedback screenshots found.');
        }

        $responses = [];

        foreach ($files as $file) {
            $manifestPath = $file . '.json';
            $metadata = Storage::exists($manifestPath)
                ? json_decode(Storage::get($manifestPath), true)
                : [];

            $metaText = collect([
                isset($metadata['url']) ? "URL: {$metadata['url']}" : null,
                isset($metadata['annotation']) ? "Annotation: {$metadata['annotation']}" : null,
                isset($metadata['viewport']) ? "Viewport: {$metadata['viewport']['width']}x{$metadata['viewport']['height']}" : null,
                isset($metadata['timestamp']) ? "Captured: {$metadata['timestamp']}" : null,
            ])->filter()->implode("\n");

            if ($metaText) {
                $responses[] = Response::text($metaText);
            }

            $responses[] = Response::image(Storage::get($file), 'image/png');
        }

        return $responses;
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `composer test -- --filter=GetDeveloperFeedback`
Expected: PASS

**Step 5: Run full test suite**

Run: `composer test`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add src/Mcp/Tools/GetDeveloperFeedback.php tests/Unit/Mcp/Tools/GetDeveloperFeedbackTest.php
git commit -m "chore: add GetDeveloperFeedback MCP tool"
```

---

## Task 4: Register MCP Server in Service Provider

**Files:**
- Modify: `src/TurboServiceProvider.php`

**Step 1: Write the test**

Add to `tests/Unit/TurboServiceProviderTest.php` (create if needed):

```php
<?php

it('registers the feedback MCP server route', function () {
    // After the service provider boots, the 'turbo-feedback' MCP local server should be registered
    $this->artisan('mcp:start', ['server' => 'turbo-feedback', '--help' => true])
        ->assertSuccessful();
});
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter=registers`
Expected: FAIL — server not registered.

**Step 3: Register the MCP server**

Modify `src/TurboServiceProvider.php`. The Laravel MCP SDK uses `routes/ai.php` in consumer apps, but as a package we need to register the route in the service provider's `packageBooted` method:

```php
use Laravel\Mcp\Facades\Mcp;
use Springloaded\Turbo\Mcp\FeedbackServer;

public function packageBooted(): void
{
    Mcp::local('turbo-feedback', FeedbackServer::class);
}
```

**Step 4: Run test to verify it passes**

Run: `composer test -- --filter=registers`
Expected: PASS

**Step 5: Commit**

```bash
git add src/TurboServiceProvider.php tests/Unit/TurboServiceProviderTest.php
git commit -m "chore: register turbo-feedback MCP server"
```

---

## Task 5: Create the Developer Feedback Skill

**Files:**
- Create: `.ai/skills/developer-feedback/SKILL.md`

**Step 1: Create the skill**

Create `.ai/skills/developer-feedback/SKILL.md`:

```markdown
---
name: developer-feedback
description: Use when the developer's message contains [feedback] or mentions visual feedback, screenshots, or browser captures. Triggers retrieval of developer-captured screenshots via MCP tool.
---

# Developer Feedback

When the developer provides visual feedback from their browser, retrieve and review the screenshots they've captured.

## Trigger

The developer will paste a string like:

- `[feedback] the header spacing is wrong`
- `[feedback]`

The text after `[feedback]` is their annotation describing the issue.

## What To Do

1. Call the `get_developer_feedback` MCP tool
2. Review all returned screenshots alongside the developer's annotation
3. Relate the visual issue to the current conversation context
4. Propose a fix or ask clarifying questions about what you see

## Important

- The screenshots show what the developer sees in their browser on the host machine
- Multiple screenshots may be returned — review all of them, oldest to newest
- Each screenshot includes metadata: the page URL, viewport size, and the developer's annotation
- If no screenshots are found, ask the developer to capture one using the Turbo Feedback browser extension
```

**Step 2: Verify skill is discoverable**

Run: `bin/turbo skills` should list `developer-feedback` among available skills.

Alternatively, check programmatically:
```bash
ls .ai/skills/developer-feedback/SKILL.md
```
Expected: File exists.

**Step 3: Commit**

```bash
git add .ai/skills/developer-feedback/SKILL.md
git commit -m "chore: add developer-feedback skill"
```

---

## Task 6: Install Command — MCP Server Registration

**Files:**
- Modify: `src/Commands/InstallCommand.php`
- Modify: `src/Services/DockerSandbox.php`

**Step 1: Write the test**

Add to `tests/Unit/InstallCommandTest.php`:

```php
it('registers the turbo-feedback MCP server in claude settings during docker setup', function () {
    // Setup: create a minimal .claude/settings.json
    $settingsDir = $this->app->basePath('.claude');
    mkdir($settingsDir, 0755, true);
    file_put_contents("{$settingsDir}/settings.json", json_encode([
        'enabledPlugins' => ['superpowers@claude-plugins-official' => true],
    ]));

    // Run install with docker setup enabled
    // (Mock sandbox creation methods to avoid Docker dependency)
    registerTestableInstallCommand([
        'sandboxExists' => true,
    ]);

    $this->artisan('turbo:install', ['--no-interaction' => true]);

    $settings = json_decode(
        file_get_contents("{$settingsDir}/settings.json"),
        true
    );

    expect($settings)->toHaveKey('mcpServers');
    expect($settings['mcpServers'])->toHaveKey('turbo-feedback');
    expect($settings['mcpServers']['turbo-feedback']['command'])->toBe('php');
    expect($settings['mcpServers']['turbo-feedback']['args'])->toContain('artisan', 'mcp:start', 'turbo-feedback');
});
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter="registers the turbo-feedback MCP"`
Expected: FAIL — no mcpServers key in settings.

**Step 3: Add MCP registration to InstallCommand**

Add a method to `src/Commands/InstallCommand.php`:

```php
protected function registerMcpServer(): void
{
    $settingsPath = base_path('.claude/settings.json');

    $settings = file_exists($settingsPath)
        ? json_decode(file_get_contents($settingsPath), true)
        : [];

    $settings['mcpServers'] ??= [];
    $settings['mcpServers']['turbo-feedback'] = [
        'command' => 'php',
        'args' => ['artisan', 'mcp:start', 'turbo-feedback'],
    ];

    file_put_contents(
        $settingsPath,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $this->components->info('Registered turbo-feedback MCP server.');
}
```

Call `$this->registerMcpServer()` from within `offerDockerSetup()`, after sandbox creation succeeds.

**Step 4: Run test to verify it passes**

Run: `composer test -- --filter="registers the turbo-feedback MCP"`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Commands/InstallCommand.php tests/Unit/InstallCommandTest.php
git commit -m "chore: register MCP server during install"
```

---

## Task 7: Install Command — Native Messaging Host Setup

**Files:**
- Create: `resources/native-messaging/host.js`
- Create: `resources/native-messaging/com.springloaded.turbo_feedback.json`
- Modify: `src/Commands/InstallCommand.php`

**Step 1: Create the native messaging host script**

Create `resources/native-messaging/host.js`:

```javascript
#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

/**
 * Chrome Native Messaging uses a specific protocol:
 * - Input: 4-byte little-endian length prefix, then JSON
 * - Output: 4-byte little-endian length prefix, then JSON
 */
function readMessage() {
  return new Promise((resolve) => {
    let lengthBuffer = Buffer.alloc(0);

    const onData = (chunk) => {
      lengthBuffer = Buffer.concat([lengthBuffer, chunk]);

      if (lengthBuffer.length >= 4) {
        const messageLength = lengthBuffer.readUInt32LE(0);
        const remaining = lengthBuffer.slice(4);

        let messageBuffer = remaining;

        const readRest = (chunk) => {
          messageBuffer = Buffer.concat([messageBuffer, chunk]);
          if (messageBuffer.length >= messageLength) {
            process.stdin.removeListener('data', readRest);
            resolve(JSON.parse(messageBuffer.slice(0, messageLength).toString()));
          }
        };

        process.stdin.removeListener('data', onData);

        if (messageBuffer.length >= messageLength) {
          resolve(JSON.parse(messageBuffer.slice(0, messageLength).toString()));
        } else {
          process.stdin.on('data', readRest);
        }
      }
    };

    process.stdin.on('data', onData);
  });
}

function sendMessage(message) {
  const json = JSON.stringify(message);
  const buffer = Buffer.alloc(4 + json.length);
  buffer.writeUInt32LE(json.length, 0);
  buffer.write(json, 4);
  process.stdout.write(buffer);
}

async function main() {
  const message = await readMessage();

  const { image, filename, annotation, url, viewport, workspace } = message;

  if (!workspace || !image || !filename) {
    sendMessage({ success: false, error: 'Missing required fields: workspace, image, filename' });
    process.exit(1);
  }

  const feedbackDir = path.join(workspace, 'storage', 'app', 'agent-captures', 'feedback');

  // Ensure directory exists
  fs.mkdirSync(feedbackDir, { recursive: true });

  // Write PNG file
  const imagePath = path.join(feedbackDir, filename);
  fs.writeFileSync(imagePath, Buffer.from(image, 'base64'));

  // Write JSON manifest
  const manifest = {
    image: filename,
    url: url || null,
    annotation: annotation || null,
    timestamp: new Date().toISOString(),
    viewport: viewport || null,
  };
  fs.writeFileSync(`${imagePath}.json`, JSON.stringify(manifest, null, 2));

  sendMessage({ success: true, path: imagePath });
}

main().catch((err) => {
  sendMessage({ success: false, error: err.message });
  process.exit(1);
});
```

**Step 2: Create the Chrome native messaging manifest template**

Create `resources/native-messaging/com.springloaded.turbo_feedback.json`:

```json
{
  "name": "com.springloaded.turbo_feedback",
  "description": "Turbo Feedback - saves browser screenshots to project workspace",
  "path": "{{HOST_SCRIPT_PATH}}",
  "type": "stdio",
  "allowed_origins": ["chrome-extension://{{EXTENSION_ID}}/"]
}
```

**Step 3: Add install method for native messaging host**

Add to `src/Commands/InstallCommand.php`:

```php
protected function installNativeMessagingHost(): void
{
    $hostScriptSource = dirname(__DIR__, 2) . '/resources/native-messaging/host.js';

    // Determine Chrome native messaging host directory
    $nativeHostDir = match (PHP_OS_FAMILY) {
        'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/Google/Chrome/NativeMessagingHosts',
        'Linux' => $_SERVER['HOME'] . '/.config/google-chrome/NativeMessagingHosts',
        default => null,
    };

    if (! $nativeHostDir) {
        $this->components->warn('Native messaging host setup is only supported on macOS and Linux.');
        return;
    }

    if (! is_dir($nativeHostDir)) {
        mkdir($nativeHostDir, 0755, true);
    }

    // Copy host script
    $hostScriptDest = $nativeHostDir . '/com.springloaded.turbo_feedback.js';
    copy($hostScriptSource, $hostScriptDest);
    chmod($hostScriptDest, 0755);

    // Write manifest with resolved path
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/resources/native-messaging/com.springloaded.turbo_feedback.json'),
        true
    );
    $manifest['path'] = $hostScriptDest;
    // Extension ID will be set after Chrome Web Store publish or during dev sideload
    // For now, use a placeholder that the developer updates
    $manifest['allowed_origins'] = ['chrome-extension://<EXTENSION_ID>/'];

    file_put_contents(
        $nativeHostDir . '/com.springloaded.turbo_feedback.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $this->components->info('Native messaging host installed.');
}
```

Call `$this->installNativeMessagingHost()` from within `offerDockerSetup()`.

**Step 4: Run tests**

Run: `composer test`
Expected: All tests pass.

**Step 5: Commit**

```bash
git add resources/native-messaging/host.js resources/native-messaging/com.springloaded.turbo_feedback.json src/Commands/InstallCommand.php
git commit -m "chore: add native messaging host and install integration"
```

---

## Task 8: Install Command — Deep Link for Extension Configuration

**Files:**
- Modify: `src/Commands/InstallCommand.php`

**Step 1: Add the deep link method**

Add to `src/Commands/InstallCommand.php`:

```php
protected function configureExtensionProject(): void
{
    // Read APP_URL from workspace .env
    $envPath = base_path('.env');
    $appUrl = null;

    if (file_exists($envPath)) {
        $env = file_get_contents($envPath);
        if (preg_match('/^APP_URL=(.+)$/m', $env, $matches)) {
            $appUrl = trim($matches[1], '"\'');
        }
    }

    if (! $appUrl) {
        $this->components->warn('APP_URL not found in .env — skipping extension configuration.');
        return;
    }

    $host = parse_url($appUrl, PHP_URL_HOST);
    $workspace = base_path();

    $confirmed = confirm(
        label: "Configure browser extension for {$host}?",
        default: true,
        hint: "Workspace: {$workspace}"
    );

    if (! $confirmed) {
        return;
    }

    $deepLink = 'turbo-feedback://add-project?' . http_build_query([
        'url' => $host,
        'workspace' => $workspace,
    ]);

    // Try to open in browser
    $opened = match (PHP_OS_FAMILY) {
        'Darwin' => exec("open " . escapeshellarg($deepLink) . " 2>/dev/null", result_code: $exitCode) !== false && $exitCode === 0,
        'Linux' => exec("xdg-open " . escapeshellarg($deepLink) . " 2>/dev/null", result_code: $exitCode) !== false && $exitCode === 0,
        default => false,
    };

    if ($opened) {
        $this->components->info('Opened browser to configure extension.');
    }

    $this->components->info("Press c to copy the URL if the browser didn't open, or Enter to skip.");

    // Read single keypress
    $key = $this->anticipate('', []);
    if (strtolower($key) === 'c') {
        exec("echo " . escapeshellarg($deepLink) . " | pbcopy 2>/dev/null || echo " . escapeshellarg($deepLink) . " | xclip -selection clipboard 2>/dev/null");
        $this->components->info('URL copied to clipboard.');
    }
}
```

Call `$this->configureExtensionProject()` from within `offerDockerSetup()`, after native messaging host installation.

**Step 2: Run tests**

Run: `composer test`
Expected: All tests pass.

**Step 3: Commit**

```bash
git add src/Commands/InstallCommand.php
git commit -m "chore: add deep link extension configuration during install"
```

---

## Task 9: Chrome Extension — Project Scaffolding

**Files:**
- Create: `extension/manifest.json`
- Create: `extension/popup.html`
- Create: `extension/popup.js`
- Create: `extension/background.js`
- Create: `extension/styles.css`

**Step 1: Create the extension manifest**

Create `extension/manifest.json`:

```json
{
  "manifest_version": 3,
  "name": "Turbo Feedback",
  "version": "1.0.0",
  "description": "Capture browser screenshots and send them to Claude in a Turbo sandbox.",
  "permissions": [
    "activeTab",
    "clipboardWrite",
    "nativeMessaging"
  ],
  "action": {
    "default_popup": "popup.html",
    "default_icon": {
      "16": "icons/icon-16.png",
      "48": "icons/icon-48.png",
      "128": "icons/icon-128.png"
    }
  },
  "background": {
    "service_worker": "background.js"
  },
  "commands": {
    "_execute_action": {
      "suggested_key": {
        "default": "Ctrl+Shift+F",
        "mac": "Command+Shift+F"
      },
      "description": "Capture screenshot and copy feedback trigger"
    }
  },
  "protocol_handlers": [
    {
      "protocol": "turbo-feedback",
      "name": "Turbo Feedback Configuration",
      "uriTemplate": "popup.html?%s"
    }
  ]
}
```

**Note:** Manifest V3 `protocol_handlers` may not work directly for custom URL schemes in all Chrome versions. The `background.js` service worker will handle `turbo-feedback://` URLs via the `chrome.runtime.onMessageExternal` API or by registering as a URL handler at the OS level. This needs validation during implementation — if `protocol_handlers` isn't supported, fall back to the `externally_connectable` pattern or a redirect page.

**Step 2: Commit the scaffold**

```bash
git add extension/
git commit -m "chore: scaffold Chrome extension"
```

---

## Task 10: Chrome Extension — Popup UI and Capture Logic

**Files:**
- Modify: `extension/popup.html`
- Modify: `extension/popup.js`
- Modify: `extension/styles.css`

**Step 1: Create the popup HTML**

Create `extension/popup.html`:

```html
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <h1>Turbo Feedback</h1>

    <div id="project-indicator" class="project">
      <span class="label">Project:</span>
      <span id="project-name">No project matched</span>
    </div>

    <textarea id="annotation" placeholder="What's the issue? (optional)" rows="2"></textarea>

    <button id="capture-btn" class="primary" disabled>Capture Screenshot</button>

    <div id="status" class="status hidden"></div>

    <details>
      <summary>Settings</summary>
      <div id="projects-list" class="projects"></div>
    </details>
  </div>

  <script src="popup.js"></script>
</body>
</html>
```

**Step 2: Create the popup JavaScript**

Create `extension/popup.js`:

```javascript
const NATIVE_HOST = 'com.springloaded.turbo_feedback';

let currentProject = null;

// Load projects from storage and match current tab
async function init() {
  const { projects = {} } = await chrome.storage.local.get('projects');
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (tab?.url) {
    const tabHost = new URL(tab.url).hostname;
    for (const [host, workspace] of Object.entries(projects)) {
      if (tabHost === host || tabHost.endsWith('.' + host)) {
        currentProject = { host, workspace };
        break;
      }
    }
  }

  const projectName = document.getElementById('project-name');
  const captureBtn = document.getElementById('capture-btn');

  if (currentProject) {
    projectName.textContent = currentProject.host;
    captureBtn.disabled = false;
  } else {
    projectName.textContent = 'No project matched';
    captureBtn.disabled = true;
  }

  renderProjects(projects);
}

function renderProjects(projects) {
  const list = document.getElementById('projects-list');
  list.innerHTML = Object.entries(projects)
    .map(([host, workspace]) => `<div class="project-item">${host} &rarr; ${workspace}</div>`)
    .join('') || '<p>No projects configured. Run <code>turbo:install</code> in a project.</p>';
}

// Capture screenshot
document.getElementById('capture-btn').addEventListener('click', async () => {
  if (!currentProject) return;

  const status = document.getElementById('status');
  const btn = document.getElementById('capture-btn');
  const annotation = document.getElementById('annotation').value.trim();

  btn.disabled = true;
  status.textContent = 'Capturing...';
  status.classList.remove('hidden', 'error');

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    const dataUrl = await chrome.tabs.captureVisibleTab(null, { format: 'png' });

    // Strip data URL prefix to get base64
    const base64Data = dataUrl.replace(/^data:image\/png;base64,/, '');

    // Generate filename
    const now = new Date();
    const timestamp = now.toISOString().replace(/[-:T]/g, '').slice(0, 14);
    const filename = `${timestamp}-feedback.png`;

    // Send to native messaging host
    const response = await chrome.runtime.sendNativeMessage(NATIVE_HOST, {
      image: base64Data,
      filename,
      annotation: annotation || null,
      url: tab.url,
      viewport: { width: tab.width, height: tab.height },
      workspace: currentProject.workspace,
    });

    if (response.success) {
      // Copy trigger string to clipboard
      const trigger = annotation ? `[feedback] ${annotation}` : '[feedback]';
      await navigator.clipboard.writeText(trigger);

      status.textContent = 'Captured! Trigger copied to clipboard.';
      setTimeout(() => window.close(), 1500);
    } else {
      throw new Error(response.error || 'Unknown error');
    }
  } catch (err) {
    status.textContent = `Error: ${err.message}`;
    status.classList.add('error');
    btn.disabled = false;
  }
});

// Handle turbo-feedback:// deep links
function handleDeepLink(url) {
  try {
    const params = new URLSearchParams(url.split('?')[1]);
    const host = params.get('url');
    const workspace = params.get('workspace');

    if (host && workspace) {
      chrome.storage.local.get('projects', ({ projects = {} }) => {
        projects[host] = workspace;
        chrome.storage.local.set({ projects }, () => {
          init(); // Refresh UI
        });
      });
    }
  } catch (e) {
    console.error('Failed to parse deep link:', e);
  }
}

// Check if opened via deep link
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.toString().includes('turbo-feedback://')) {
  handleDeepLink(decodeURIComponent(urlParams.toString()));
}

init();
```

**Step 3: Create basic styles**

Create `extension/styles.css`:

```css
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  width: 320px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  color: #1a1a1a;
}

.container { padding: 16px; }

h1 { font-size: 16px; margin-bottom: 12px; }

.project {
  background: #f5f5f5;
  padding: 8px 12px;
  border-radius: 6px;
  margin-bottom: 12px;
  font-size: 13px;
}

.label { color: #666; }

textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-family: inherit;
  font-size: 13px;
  resize: vertical;
  margin-bottom: 12px;
}

.primary {
  width: 100%;
  padding: 10px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.primary:hover { background: #1d4ed8; }
.primary:disabled { background: #94a3b8; cursor: not-allowed; }

.status {
  margin-top: 12px;
  padding: 8px;
  border-radius: 6px;
  background: #ecfdf5;
  color: #065f46;
  font-size: 13px;
  text-align: center;
}

.status.error { background: #fef2f2; color: #991b1b; }
.status.hidden { display: none; }

details { margin-top: 16px; }
summary { cursor: pointer; color: #666; font-size: 13px; }

.projects { margin-top: 8px; }
.project-item {
  padding: 4px 0;
  font-size: 12px;
  color: #444;
  border-bottom: 1px solid #eee;
}
```

**Step 4: Commit**

```bash
git add extension/
git commit -m "chore: implement extension popup UI and capture logic"
```

---

## Task 11: Chrome Extension — Background Service Worker

**Files:**
- Modify: `extension/background.js`

**Step 1: Create the background service worker**

Create `extension/background.js`:

```javascript
// Handle turbo-feedback:// URL protocol
// This registers the extension to handle the custom protocol
chrome.runtime.onStartup.addListener(() => {
  // No-op — just ensures the service worker is active
});

// Handle messages from external sources (for deep link fallback)
chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
  if (message.action === 'add-project' && message.url && message.workspace) {
    chrome.storage.local.get('projects', ({ projects = {} }) => {
      projects[message.url] = message.workspace;
      chrome.storage.local.set({ projects }, () => {
        sendResponse({ success: true });
      });
    });
    return true; // Keep channel open for async response
  }
});

// Handle keyboard shortcut — capture without opening popup
chrome.commands.onCommand.addListener(async (command) => {
  if (command === '_execute_action') {
    // The popup will open automatically via _execute_action
    // No additional handling needed
  }
});
```

**Step 2: Commit**

```bash
git add extension/background.js
git commit -m "chore: add extension background service worker"
```

---

## Task 12: Extension Icons

**Files:**
- Create: `extension/icons/icon-16.png`
- Create: `extension/icons/icon-48.png`
- Create: `extension/icons/icon-128.png`

**Step 1: Create placeholder icons**

Generate simple placeholder icons (colored squares or use a camera/screenshot icon). These can be replaced with proper branding later.

For now, create the directory and add a note:

```bash
mkdir -p extension/icons
```

Create `extension/icons/README.md`:
```markdown
# Extension Icons

Replace these placeholder icons with branded versions:
- `icon-16.png` — 16x16px (toolbar)
- `icon-48.png` — 48x48px (extensions page)
- `icon-128.png` — 128x128px (Chrome Web Store)
```

**Step 2: Commit**

```bash
git add extension/icons/
git commit -m "chore: add extension icon placeholders"
```

---

## Task 13: Integration Testing — End-to-End Flow

**Files:**
- Create: `tests/Integration/FeedbackFlowTest.php`

**Step 1: Write integration test**

Create `tests/Integration/FeedbackFlowTest.php`:

```php
<?php

use Illuminate\Support\Facades\Storage;

it('MCP tool returns feedback images written to workspace', function () {
    Storage::fake('local');

    $feedbackDir = 'agent-captures/feedback';

    // Simulate what the native messaging host writes
    $imageData = file_get_contents(__DIR__ . '/../fixtures/test-screenshot.png')
        ?: 'fake-png-data';

    Storage::put("{$feedbackDir}/20260307-143022-feedback.png", $imageData);
    Storage::put("{$feedbackDir}/20260307-143022-feedback.png.json", json_encode([
        'image' => '20260307-143022-feedback.png',
        'url' => 'https://myapp.test/dashboard',
        'annotation' => 'header spacing is wrong',
        'timestamp' => '2026-03-07T14:30:22Z',
        'viewport' => ['width' => 1440, 'height' => 900],
    ]));

    $tool = app(\Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback::class);
    $request = new \Laravel\Mcp\Request([]);
    $result = $tool->handle($request);

    expect($result)->toBeArray();
    expect($result)->not->toBeEmpty();
});

it('MCP tool returns empty message when no feedback exists', function () {
    Storage::fake('local');

    $tool = app(\Springloaded\Turbo\Mcp\Tools\GetDeveloperFeedback::class);
    $request = new \Laravel\Mcp\Request([]);
    $result = $tool->handle($request);

    expect($result)->toBeInstanceOf(\Laravel\Mcp\Response::class);
});
```

**Step 2: Create test fixture**

Create a minimal 1x1 PNG for testing:

```bash
mkdir -p tests/fixtures
# Create a minimal valid PNG (can also use any small screenshot)
printf '\x89PNG\r\n\x1a\n' > tests/fixtures/test-screenshot.png
```

**Step 3: Run tests**

Run: `composer test`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Integration/FeedbackFlowTest.php tests/fixtures/
git commit -m "test: add integration test for feedback flow"
```

---

## Task 14: Final Verification

**Step 1: Run full test suite**

Run: `composer test`
Expected: All tests pass.

**Step 2: Run static analysis**

Run: `composer analyse`
Expected: No errors.

**Step 3: Run code formatting**

Run: `composer format`

**Step 4: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: apply code formatting"
```

**Step 5: Verify extension loads in Chrome**

1. Open Chrome → `chrome://extensions/`
2. Enable "Developer mode"
3. Click "Load unpacked" → select `extension/` directory
4. Verify extension icon appears in toolbar
5. Click icon → verify popup renders
6. Note the extension ID for native messaging manifest

---

## Execution Notes

- Tasks 1-6 are PHP/Laravel work within the Turbo package
- Tasks 7-8 are install command integration (PHP)
- Tasks 9-12 are Chrome extension work (JavaScript)
- Tasks 13-14 are testing and verification
- The native messaging host manifest needs the real extension ID after Chrome assigns one (Task 12 note)
- The `protocol_handlers` approach for `turbo-feedback://` may need adjustment based on Chrome's actual support — validate during Task 9 implementation
