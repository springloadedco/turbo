---
name: fix-native-binaries
description: Background knowledge about npm native binary corruption in Docker sandboxes
user-invocable: false
---

# Native Binary Corruption in Docker Sandboxes

## What You Need to Know

This sandbox uses **file synchronization** (not volume mounts) for the workspace directory. The sync layer **corrupts native binary files** during `npm install` extraction. This affects all native binaries: esbuild, rollup, tailwindcss oxide, lightningcss, sharp, etc.

## How the npm Wrapper Handles This

An npm wrapper function is active in this sandbox. It automatically:

1. Runs `npm install --ignore-scripts` in the workspace (extracts packages, skips postinstall that would crash on corrupted binaries)
2. Performs a "shadow install" outside the workspace at `/home/agent/.npm-shadow/` (gets identical packages with intact binaries)
3. Copies native binaries from the shadow dir back to the workspace, replacing corrupted versions

**You don't need to do anything special** — just run `npm install` normally and the wrapper handles it.

## Recognizing the Problem

If you see any of these errors, native binary corruption is the cause:

- `SIGSEGV` — "minpc or maxpc invalid", "panic before malloc heap initialized"
- `SIGILL` — Illegal instruction
- `SIGBUS` — Bus error
- `WebAssembly.CompileError` — corrupted .wasm files
- esbuild or rollup crashing during `npm install` postinstall or during build

## Manual Fix (Fallback)

If the npm wrapper isn't working or you need to fix binaries manually:

```bash
fix-native-binaries /path/to/workspace
```

This runs the shadow install + binary copy process directly.

## Rules

- **Always use `--ignore-scripts`** if running npm install manually without the wrapper (e.g., `command npm install --ignore-scripts`)
- **Never install WASM fallback packages** (esbuild-wasm, @rollup/wasm-node) — the native binaries work fine once the corruption is fixed
- **The corruption only affects the workspace directory** — files outside the workspace (e.g., `/home/agent/`) are not affected
