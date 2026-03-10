# Browser Feedback Extension

**Date:** 2026-03-07
**Status:** Draft

## Problem

When Claude runs inside a Turbo Docker sandbox, developers lose the ability to paste screenshots into the conversation. In normal Claude Code (no sandbox), you can paste images directly into the CLI. The sandbox breaks this because there's no clipboard bridge into the container.

Developers need to provide visual feedback — "the spacing is wrong here", "the header is broken" — and describing layout issues in text is slow and imprecise.

## Solution

A three-component system: a Chrome browser extension, a native messaging host, and a Laravel MCP server.

1. Developer spots a visual issue in their browser
2. Clicks the extension icon (or keyboard shortcut) to capture a screenshot
3. Extension sends the image to the native messaging host, which writes it to the project workspace
4. Extension copies a trigger string (e.g., `[feedback] the spacing is off`) to the clipboard
5. Developer pastes the string into the terminal
6. A skill teaches Claude to call the MCP `get_developer_feedback` tool when it sees the trigger
7. The MCP tool reads the screenshot from the workspace and returns it as an image
8. Claude sees the screenshot and responds to the visual issue

## Architecture

```
+---------------------+     native msg      +------------------------+
|  Browser Extension   | ------------------> |  Native Messaging Host |
|  (Chrome, host)      |                     |  (Node.js, host)       |
|                      |                     |                        |
|  - Capture tab       |                     |  - Receives image data |
|  - Copy trigger to   |                     |  - Writes PNG + JSON   |
|    clipboard         |                     |    to workspace        |
+---------------------+                     +----------+-------------+
                                                        |
                                                        | writes to
                                                        v
+---------------------+    calls tool        +----------+-------------+
|  Claude (sandbox)    | <-----------------> |  Workspace (mounted)   |
|                      |   MCP stdio         |  storage/app/          |
|  - Receives pasted   |                     |  agent-captures/       |
|    trigger string    |                     |  feedback/             |
|  - Calls MCP tool    | <-- reads from --+  +------------------------+
|  - Sees screenshot   |                  |
+---------------------+                  |
                                          |
                          +---------------+--------+
                          |  MCP Server (local)    |
                          |  Laravel Artisan cmd   |
                          |                        |
                          |  - get_developer_      |
                          |    feedback tool        |
                          |  - Returns image(s)    |
                          +------------------------+
```

## Component Details

### 1. Browser Extension (Chrome/Arc)

**Manifest V3** with permissions: `activeTab`, `clipboardWrite`, `tabs`, `nativeMessaging`.

**UI:** Clicking the extension icon (or keyboard shortcut, e.g., `Ctrl+Shift+F`) shows a popup with:
- "Capture" button (primary action)
- Optional annotation text field (e.g., "the card spacing is wrong")
- Project indicator showing the matched workspace for the current tab

**On capture:**
1. `chrome.tabs.captureVisibleTab()` captures the viewport as a PNG data URL
2. Extension sends the image + metadata to the native messaging host
3. Extension copies trigger string to clipboard: `[feedback] the card spacing is wrong` (or `[feedback]` if no annotation)

**Project matching:** The extension maintains URL-to-workspace mappings in its storage (e.g., `myapp.test` -> `/Users/dev/Sites/myapp`). It matches the current tab's URL to determine which workspace to write to.

**Custom URL protocol registration:** The extension registers the `turbo-feedback://` protocol. This allows `turbo:install` to configure project mappings automatically (see Install Integration below).

### 2. Native Messaging Host

A Node.js script registered with Chrome via a native messaging manifest at `~/Library/Application Support/Google/Chrome/NativeMessagingHosts/com.springloaded.turbo_feedback.json`.

**Installed once globally** during `turbo:install` (first run). Project-agnostic — it writes to whatever workspace path the extension provides.

**Receives from extension:**
```json
{
  "image": "<base64 PNG data>",
  "filename": "2026-03-07-143022-feedback.png",
  "annotation": "the spacing is off",
  "url": "https://myapp.test/dashboard",
  "viewport": { "width": 1440, "height": 900 },
  "workspace": "/Users/dev/Sites/myapp"
}
```

**Actions:**
1. Ensures `{workspace}/storage/app/agent-captures/feedback/` exists
2. Writes the PNG file
3. Writes a JSON manifest alongside it (`{filename}.json`):
   ```json
   {
     "image": "2026-03-07-143022-feedback.png",
     "url": "https://myapp.test/dashboard",
     "annotation": "the spacing is off",
     "timestamp": "2026-03-07T14:30:22Z",
     "viewport": { "width": 1440, "height": 900 }
   }
   ```
4. Responds with `{ "success": true, "path": "..." }`

### 3. MCP Server (Laravel)

A Laravel MCP server using the Laravel MCP SDK, running as a local (stdio) Artisan command inside the sandbox.

**Server:** Registered as a local MCP server in the project's MCP configuration during `turbo:install`.

**Tool: `get_developer_feedback`**

Reads all images from `storage/app/agent-captures/feedback/`, sorted chronologically (oldest first, most recent last). Returns each image with its metadata.

```php
#[Description('Retrieves developer feedback screenshots captured from the browser.')]
class GetDeveloperFeedbackTool extends Tool
{
    public function handle(Request $request): Response
    {
        // Read all PNG files from feedback directory, sorted by timestamp
        // For each, load the image and its companion JSON manifest
        // Return images with metadata (annotation, url, viewport)
    }
}
```

Response uses `Response::image()` to return proper image content that Claude can see.

The MCP server is a dumb reader — it returns everything in the feedback directory. The browser extension controls what's there.

### 4. Skill

A Turbo skill (`.ai/skills/developer-feedback/SKILL.md`) that teaches Claude:
- When the developer's message contains `[feedback]`, call the `get_developer_feedback` MCP tool
- Review the screenshot(s) alongside the developer's annotation
- Respond to the visual issue in context of the current conversation

### 5. Install Integration

During `turbo:install`, after confirming `APP_URL` from `.env`:

1. **Register native messaging host** (first time only): Write the Chrome native messaging manifest and the Node.js script
2. **Configure project mapping:** Open a `turbo-feedback://` deep link that passes `url` and `workspace` to the extension:
   ```
   turbo-feedback://add-project?url=myapp.test&workspace=/Users/dev/Sites/myapp
   ```
   Auto-opens via `open` (macOS) / `xdg-open` (Linux). Falls back to "Press c to copy the URL if the browser didn't open" (same pattern as Claude auth flow).
3. **Register MCP server:** Add the local MCP server to the project's Claude configuration

## File Layout

```
storage/app/agent-captures/feedback/
  2026-03-07-143022-feedback.png
  2026-03-07-143022-feedback.png.json
  2026-03-07-143512-feedback.png
  2026-03-07-143512-feedback.png.json
```

Already gitignored by Laravel's default `storage/app/` gitignore.

## Open Questions

- **Full page capture:** v1 captures the visible viewport. Full-page scrolling capture could be a future enhancement.
- **Region selection:** Could offer a crop/select mode before saving. Adds complexity — defer to v2.
- **Cleanup:** Should the extension or MCP server clear old feedback images? Or leave that to the developer?
- **Multiple images per tool call:** The Laravel MCP SDK may need to return multiple images in a single tool response. Need to verify SDK support for multi-content responses.
- **Extension distribution:** Chrome Web Store vs. manual install via developer mode. CWS adds review overhead but is more polished.
