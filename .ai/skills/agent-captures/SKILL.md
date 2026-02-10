---
name: agent-captures
description: Use when taking screenshots, PDFs, or video recordings with agent-browser in a Laravel project. Applies to any visual capture command including screenshot, pdf, and record.
allowed-tools: Bash(agent-browser:*), Bash(mkdir:*)
---

# Agent Captures

Save all agent-browser visual captures to `storage/app/agent-captures/` instead of temp directories or the project root.

## The Rule

**Every** `agent-browser screenshot`, `pdf`, or `record` command MUST save to `storage/app/agent-captures/`.

```bash
# Ensure the directory exists first
mkdir -p storage/app/agent-captures

# Screenshots
agent-browser screenshot storage/app/agent-captures/homepage.png
agent-browser screenshot --full storage/app/agent-captures/homepage-full.png

# PDFs
agent-browser pdf storage/app/agent-captures/checkout.pdf

# Video recordings
agent-browser record start storage/app/agent-captures/login-flow.webm
```

**Never use:**
- `agent-browser screenshot` with no path (saves to /tmp)
- `agent-browser screenshot ./something.png` (saves to project root)
- Any path outside `storage/app/agent-captures/`

## Why

Your human partner reviews these captures. Files in `/tmp` disappear. Files in the project root create clutter and git noise. `storage/app/agent-captures/` is:
- Persistent and predictable
- Already gitignored by Laravel
- Easy to browse in one place

## File Naming

Use descriptive names that tell your partner what they're looking at:

```bash
# Good - descriptive, scannable
storage/app/agent-captures/checkout-layout-bug.png
storage/app/agent-captures/dashboard-after-fix.png
storage/app/agent-captures/login-flow.webm

# Bad - generic, meaningless
storage/app/agent-captures/screenshot.png
storage/app/agent-captures/page1.png
storage/app/agent-captures/capture.pdf
```

## Quick Debugging Is Not an Exception

Even for "quick" screenshots during debugging, use the same path. It takes zero extra effort:

```bash
# This is just as fast
agent-browser screenshot storage/app/agent-captures/checkout-bug.png

# And your partner can actually find it later
```

"Throwaway debug artifacts" is not a reason to skip the standard path. Your partner asked to see the screenshot — make it findable.
