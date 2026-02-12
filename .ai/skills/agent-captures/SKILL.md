---
name: agent-captures
description: Use when taking screenshots, PDFs, or video recordings with agent-browser in a Laravel project. Applies to any visual capture command including screenshot, pdf, and record.
allowed-tools: Bash(agent-browser:*), Bash(mkdir:*), Bash(npm:run build), Bash(rm:public/hot)
---

# Agent Captures

Save all agent-browser visual captures to `storage/app/agent-captures/` instead of temp directories or the project root.

## Before You Capture

**Always build frontend assets before previewing.** The sandbox can't reach the host's Vite dev server, so pages that load JS/CSS via Vite will appear blank.

```bash
# If the host was running `npm run dev`, Laravel will try to use dev server URLs.
# Delete the hot file so Laravel falls back to the build manifest.
rm -f public/hot

# Build assets using the sandbox's Linux-native node_modules
npm run build
```

**Why this is necessary:**
- Laravel's `@vite` directive checks for `public/hot` — if it exists, it serves `http://localhost:5173/...` URLs
- `localhost:5173` inside the sandbox is the sandbox itself, not the host's Vite dev server
- Deleting `public/hot` and building makes Laravel serve static assets from `public/build/manifest.json`
- The host's `npm run dev` will recreate `public/hot` when restarted — no lasting impact

**Do this every time**, even if you think assets are already built. It's fast and avoids blank-page debugging.

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
