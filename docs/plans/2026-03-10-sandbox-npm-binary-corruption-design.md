# Sandbox npm Binary Corruption — Design

**Date:** 2026-03-10
**Status:** Implemented
**Depends on:** [Research findings](./2026-03-10-sandbox-npm-binary-corruption.md)

## Summary

Fix npm install and build failures in Docker sandboxes caused by the workspace file sync corrupting native binaries during extraction. The solution combines an npm wrapper function (automatic) with a Claude skill (fallback knowledge).

**Key insight:** Copying intact binaries back into the workspace doesn't work — the file sync re-corrupts them. Symlinks work because the actual binary content stays outside the synced directory.

## Components

### 1. npm wrapper function

Baked into the Docker image via `/etc/sandbox-persistent.sh`. Intercepts every `npm install`, `npm i`, and `npm ci` call.

**Flow:**

```
npm install <args>
    │
    ├─ 1. Run `npm install --ignore-scripts <args>` in workspace
    │     (extracts packages, skips postinstall that would crash)
    │
    ├─ 2. Call fix-native-binaries, which:
    │     a. Copies package.json + lockfile to shadow dir (/home/agent/.npm-shadow/)
    │     b. Runs `npm install --ignore-scripts` in shadow dir
    │        (gets identical packages with intact binaries)
    │     c. Symlinks native binaries from shadow → workspace
    │        (symlinks keep binary content outside the synced directory)
    │
    └─ 3. Return original npm exit code
```

**Location in image:** Appended to `/etc/sandbox-persistent.sh` via `RUN` in Dockerfile. This file is sourced by `CLAUDE_ENV_FILE` before every bash command, making the function always available.

**Binary detection:** Rather than hardcoding package names, scan for:
- Executable binaries in `node_modules/@*/*/bin/` and `node_modules/*/bin/`
- Native addon `.node` files in `node_modules/@*/*/` and `node_modules/*/`

### 2. fix-native-binaries script

Standalone script at `/usr/local/bin/fix-native-binaries`. Called by the npm wrapper. Can also be run manually as a fallback.

**Arguments:** `fix-native-binaries <workspace-path>`

**Behavior:**
1. Copies `package.json` and `package-lock.json` to `/home/agent/.npm-shadow/`
2. Runs `npm install --ignore-scripts` in shadow dir (skipped if hash matches)
3. Finds all native binaries and executables in shadow `node_modules/`
4. Replaces workspace copies with symlinks to the shadow dir

### 3. Sandbox-only skill

Baked into the Docker image at `/home/agent/.claude/skills/fix-native-binaries/SKILL.md`. Auto-discovered by Claude Code as a personal/user-level skill.

**Properties:**
- `user-invocable: false` — background knowledge, not a slash command
- Not published to the project repo — only exists inside the sandbox image

**Content teaches Claude:**
- The root cause (workspace file sync corrupts native binaries)
- Symptoms to recognize (SIGILL, SIGSEGV, SIGBUS during npm install or build)
- That the npm wrapper handles this automatically
- Manual fix as fallback: `fix-native-binaries /path/to/workspace`
- To always use `npm install --ignore-scripts` if the wrapper somehow fails

### 4. Setup script (simplified)

`setup-sandbox` is reduced to host entry management only. All npm/binary logic moves to the npm wrapper and fix-native-binaries script.

### 5. Dockerfile changes

```dockerfile
# Fix native binary corruption from workspace file sync
COPY docker/fix-native-binaries.sh /usr/local/bin/fix-native-binaries
RUN chmod +x /usr/local/bin/fix-native-binaries

# npm wrapper — auto-fixes native binaries after every install
RUN printf '%s\n' \
  'npm() { ... }' \
  >> /etc/sandbox-persistent.sh

# Sandbox-only skill — teaches Claude about the workaround
COPY --chown=agent:agent docker/skills/fix-native-binaries/SKILL.md /home/agent/.claude/skills/fix-native-binaries/SKILL.md
```

## File inventory

| File | Location in image | Purpose |
|------|-------------------|---------|
| `docker/fix-native-binaries.sh` | `/usr/local/bin/fix-native-binaries` | Shadow install + symlink script |
| `docker/setup-sandbox.sh` | `/usr/local/bin/setup-sandbox` | Host entries only (simplified) |
| `docker/skills/fix-native-binaries/SKILL.md` | `/home/agent/.claude/skills/fix-native-binaries/SKILL.md` | Fallback knowledge for Claude |
| `Dockerfile` | N/A | Wires everything together |

## Edge cases

**No package.json:** npm wrapper passes through to real npm. No shadow install needed.

**No native binaries:** Shadow install runs but no binaries to symlink. Small overhead (~2-5s) but harmless.

**Agent runs npm outside workspace:** The wrapper checks if `$PWD` is within the workspace (synced directory). If not, passes through to real npm without modification.

**Lockfile type:** Supports `package-lock.json` (npm). `yarn.lock`/`pnpm-lock.yaml` are not handled by this wrapper — those tools have their own install commands.

**Shadow dir already populated:** Check hash of package.json + lockfile. Skip shadow install if unchanged.

**Why symlinks, not copies:** The file sync layer re-corrupts any binary content written into the workspace, including copies of good binaries. Symlinks work because only the symlink metadata lives in the workspace — the actual binary content stays at `/home/agent/.npm-shadow/` outside the synced directory.

## What this replaces

- All esbuild-wasm logic (removed — native binary works when not corrupted)
- All @rollup/wasm-node logic (removed — same reason)
- NODE_PATH / DEPS_DIR isolation approach (removed)
- The old setup-sandbox npm/node_modules management (removed)
