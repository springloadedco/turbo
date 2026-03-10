---
name: developer-feedback
description: Use when the developer's message contains [feedback] or mentions visual feedback, screenshots, or browser captures they want you to review. Triggers retrieval of developer-captured screenshots via MCP tool.
---

# Developer Feedback

When the developer provides visual feedback from their browser, retrieve and review the screenshots they've captured.

## Trigger

The developer will paste a string like:

- `[feedback] the header spacing is wrong`
- `[feedback]`

The text after `[feedback]` is their annotation describing the issue.

## What To Do

1. Call the `get-developer-feedback` MCP tool
2. Review all returned screenshots alongside the developer's annotation
3. Relate the visual issue to the current conversation context
4. Propose a fix or ask clarifying questions about what you see

## Important

- The screenshots show what the developer sees in their browser on the host machine
- Multiple screenshots may be returned -- review all of them, oldest to newest
- Each screenshot includes metadata: the page URL, viewport size, and the developer's annotation
- If no screenshots are found, ask the developer to capture one using the Turbo Feedback browser extension
