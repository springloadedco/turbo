# Sandbox npm Binary Corruption — Research Findings

**Date:** 2026-03-10
**Status:** Implemented (workaround deployed)
**Branch:** `main`

## Problem

`npm install` and `npm run build` fail in Docker sandboxes for projects with native binary dependencies (esbuild, rollup, etc). These tools are used by Vite, which is the default bundler in Laravel 11/12 projects.

## Root Cause: Workspace File Sync Corrupts Native Binaries

Docker sandboxes use **file synchronization, not volume mounting** for the workspace directory. From the [architecture docs](https://docs.docker.com/ai/sandboxes/architecture/):

> "This is file synchronization, not volume mounting. Files are copied between host and VM."

When `npm install` extracts packages, the sync layer **corrupts native binary files during extraction**. The corruption is a race condition between npm writing files and the sync layer copying them.

### Evidence

All tests performed inside a running Docker sandbox (`docker/sandbox-templates:claude-code` base image, linux arm64).

| Location | npm install | Binary integrity | Binary runs? |
|---|---|---|---|
| `/home/agent/test-build/` (outside workspace) | Succeeds | Correct MD5 | Yes |
| `/Users/.../workspace/` (inside workspace) | Succeeds with `--ignore-scripts` | **Wrong MD5**, same file size, valid ELF header | **No** — SIGILL/SIGSEGV/SIGBUS |
| Workspace after copying good binary in | N/A | Correct MD5 | Yes |

- ELF magic bytes (`7f 45 4c 46`) are correct, but file content is scrambled
- Different MD5 hash on every install attempt
- Both esbuild (Go binary) and rollup (Rust/C binary) are affected
- `npm install` without `--ignore-scripts` fails because esbuild's postinstall (`install.js`) tries to validate the binary immediately after extraction

### Corruption patterns observed

- `SIGSEGV` — Go runtime panic: `minpc or maxpc invalid`, `panic before malloc heap initialized`
- `SIGILL` — Illegal instruction (corrupted CPU instructions)
- `SIGBUS` — Bus error (memory-mapped file issue)
- WebAssembly `CompileError` — when esbuild-wasm's `.wasm` file is corrupted by the same mechanism

## What Does NOT Cause the Problem

- **Seccomp profiles** — Sandboxes are microVMs with their own kernel, not containers. No host seccomp applies.
- **Platform mismatch** — The VM runs linux-arm64 natively on Apple Silicon via `virtualization.framework`.
- **esbuild or rollup bugs** — Both native binaries work perfectly when installed outside the workspace.
- **Ephemeral filesystem** — The sandbox filesystem persists until removed. `/home/agent/` is persistent.

## Proven Working Solution (Manual)

```bash
# 1. Install in workspace with --ignore-scripts (packages extracted, no postinstall race)
cd /workspace/project
npm install --ignore-scripts

# 2. Install same deps OUTSIDE workspace (gets intact binaries)
SHADOW="/home/agent/.npm-shadow"
mkdir -p "$SHADOW"
cp package.json package-lock.json "$SHADOW/"
cd "$SHADOW" && npm install --ignore-scripts

# 3. Copy native binaries from shadow to workspace
cd /workspace/project
for bin in node_modules/@esbuild/*/bin/esbuild; do
  shadow_bin="$SHADOW/$bin"
  [ -f "$shadow_bin" ] && cp "$shadow_bin" "$bin"
done
for bin in node_modules/@rollup/*/rollup.*.node; do
  shadow_bin="$SHADOW/$bin"
  [ -f "$shadow_bin" ] && cp "$shadow_bin" "$bin"
done

# 4. Build works
npm run build
```

## Open Design Questions

### Setup script vs skill vs npm wrapper

**Setup script alone fails** because the agent will run `npm install <new-package>` during the session, re-triggering esbuild's postinstall against the corrupted binary.

**Options under consideration:**

1. **npm wrapper function** — intercept every `npm install` call, automatically apply `--ignore-scripts` and the shadow-install-then-copy pattern. Baked into the Docker image via `/etc/sandbox-persistent.sh`.

2. **Skill** — teach the sandbox Claude to use `--ignore-scripts` and the shadow install pattern. More flexible but requires Claude to learn/remember the pattern.

3. **Hybrid** — npm wrapper handles the common case automatically; skill provides fallback knowledge.

### Scope of affected binaries

Known affected:
- `@esbuild/linux-arm64` (Go binary, ~9.7MB)
- `@rollup/rollup-linux-arm64-gnu` (native addon, ~1.8MB)
- `@rollup/rollup-linux-arm64-musl` (native addon)

Potentially affected: any npm package with native binaries extracted to the workspace. Examples:
- `@tailwindcss/oxide-linux-arm64-gnu`
- `lightningcss-linux-arm64-gnu`
- `sharp`, `better-sqlite3`, etc.

A robust solution must handle arbitrary native binaries, not just esbuild and rollup.

### Previous failed approaches (and why)

1. **Install to `/home/agent/.sandbox-deps/` with NODE_PATH** — Used `npm install --no-save` without `--ignore-scripts`. Postinstall crashed. Also incorrectly assumed filesystem was ephemeral.

2. **esbuild-wasm fallback** — Installed esbuild-wasm to workspace. The `.wasm` file was corrupted by the same file sync issue. Setting `ESBUILD_BINARY_PATH` to the WASM binary caused a cascade: esbuild's postinstall rewrote `bin/esbuild` to delegate to the WASM binary, which tried to load the corrupted `.wasm` file.

3. **Install directly to workspace** — Without `--ignore-scripts`, esbuild's postinstall runs immediately and crashes on the corrupted binary. npm rolls back the install.

## Sandbox Architecture Reference

- **Type:** microVM (not container) — `virtualization.framework` on macOS, Hyper-V on Windows
- **Persistence:** VM and contents persist until `docker sandbox rm`
- **Workspace:** Bidirectional file sync at matching absolute paths
- **Network:** HTTP/HTTPS filtering proxy at `host.docker.internal:3128`
- **User:** `agent` (non-root, has sudo)
- **Base image:** `docker/sandbox-templates:claude-code` (Ubuntu 25.10, Node.js, Go, Python, etc.)
