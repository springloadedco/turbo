# Native Binary Fix for Docker Sandboxes

## The Problem

Docker sandboxes use **file synchronization** (not volume mounts) for the workspace directory. This sync layer corrupts native binary files — both during `npm install` extraction and when copying files into the workspace. Affected binaries include esbuild, rollup, tailwindcss oxide, lightningcss, and any other npm package with native components.

Symptoms:
- `SIGSEGV` — "minpc or maxpc invalid", "panic before malloc heap initialized"
- `SIGILL` — Illegal instruction
- `SIGBUS` — Bus error
- `npm install` failing during postinstall scripts
- `vite build` crashing immediately

## How It Works

The fix has three components baked into the Docker image:

### 1. npm Wrapper Function

A shell function in `/etc/sandbox-persistent.sh` intercepts `npm install`, `npm i`, and `npm ci` calls inside workspace directories. It:

1. Adds `--ignore-scripts` to prevent postinstall crashes on corrupted binaries
2. Calls `fix-native-binaries` to replace corrupted files with working symlinks

Non-install commands (`npm run`, `npm test`, etc.) and installs outside the workspace pass through unmodified.

### 2. `fix-native-binaries` Script

Located at `/usr/local/bin/fix-native-binaries`. Called automatically by the npm wrapper, or manually as a fallback.

**Usage:** `fix-native-binaries <workspace-path>`

**What it does:**
1. Runs a "shadow install" at `/home/agent/.npm-shadow/` — outside the workspace where file sync doesn't interfere
2. Finds native binaries and `.node` addon files in the shadow `node_modules/`
3. Replaces the corrupted workspace copies with **symlinks** to the intact shadow copies

Symlinks are the key: the actual binary content stays outside the synced directory, so the file sync can't corrupt it. Copying files back into the workspace doesn't work — the sync layer re-corrupts them.

The shadow install is cached using an md5 hash of `package.json` + `package-lock.json`. Subsequent runs skip the install if deps haven't changed.

### 3. Sandbox-Only Claude Skill

A skill at `/home/agent/.claude/skills/fix-native-binaries/SKILL.md` teaches the sandbox Claude agent about the problem and its solution. This is background knowledge (`user-invocable: false`), not a slash command. It only exists inside the Docker image — it is not published to project repos.

## Files

| Source | Image location | Purpose |
|--------|---------------|---------|
| `docker/fix-native-binaries.sh` | `/usr/local/bin/fix-native-binaries` | Shadow install + symlink script |
| `docker/setup-sandbox.sh` | `/usr/local/bin/setup-sandbox` | Host entry management |
| `docker/skills/fix-native-binaries/SKILL.md` | `/home/agent/.claude/skills/fix-native-binaries/SKILL.md` | Agent knowledge |
| `Dockerfile` | — | npm wrapper function + wiring |

## Limitations

- **npm only.** The wrapper intercepts `npm install/i/ci`. Yarn, pnpm, and bun are not intercepted. The `fix-native-binaries` script itself is package-manager agnostic and can be run manually after any package manager installs to `node_modules/`.
- **Workspace detection** uses path prefix matching (`/Users/*` and `/home/*/workspace/*`). Installs in other locations pass through to real npm.

## Manual Fallback

If the npm wrapper isn't active or something goes wrong:

```bash
# Install without running postinstall scripts
command npm install --ignore-scripts

# Fix the corrupted binaries
fix-native-binaries /path/to/workspace
```

## Background

See the [research findings](../plans/2026-03-10-sandbox-npm-binary-corruption.md) and [design document](../plans/2026-03-10-sandbox-npm-binary-corruption-design.md) for the full investigation and design process.
