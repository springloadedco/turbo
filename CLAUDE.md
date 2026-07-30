# Turbo

Springloaded's sbx kit and skills for Laravel development with Claude Code.

## Overview

This repo is **not** a Composer package. It ships two things:

1. **`kit/`** — a [Docker Sandboxes](https://docs.docker.com/ai/sandboxes/) kit (`kind: sandbox`)
   that extends the built-in `claude` agent with a Laravel toolchain, a per-sandbox network
   allowlist and agent memory. Published to Docker Hub as an OCI artifact.
2. **`skills/`** — Laravel and GitHub skills installed per-project with
   `npx skills add springloadedco/turbo`.

The `Dockerfile` builds the template image the kit points at
(`docker.io/springloadedco/turbo:latest`), published by `.github/workflows/publish-sandbox.yml`.

## Structure

| Path | Purpose |
|---|---|
| `Dockerfile` | Template image — PHP, Composer, Node 22, Chromium, agent-browser |
| `kit/spec.yaml` | The kit |
| `kit/files/home/` | Files copied into `/home/agent/` — currently the agent-browser skill |
| `skills/` | Publishable skills (`npx skills` well-known location) |
| `skills.sh.json` | Skill groupings shown by `npx skills add` |
| `.agents/skills/` | This repo's **own** dev skills — not published, tracked in `skills-lock.json` |

Don't put publishable skills in `.agents/skills/`; that directory is this repo's own tooling.

## Working on the kit

```bash
sbx kit validate ./kit/
sbx kit inspect ./kit/ --json | jq '.manifest, .caps, .warnings'
sbx kit pack ./kit/ -o /tmp/turbo-kit.zip

sbx run --kit ./kit --name turbo-probe turbo
sbx exec turbo-probe -- php -v
sbx rm turbo-probe
```

Local directory references are exempt from both the pinning rule and the source allowlist, so
iterate against the working copy. See `kit/README.md` for details.

### Kit facts worth not relearning

- **`schemaVersion` must stay `"1"`.** The current v2 grammar (`permissions:`, `setup:`,
  `agentInstructions:`) is rejected outright by sbx v0.37.1 — strict decode, `field not found in
  type spec.SpecFile`. v1 normalises to the same canonical model.
- **The two `validate` warnings are expected.** `network.allowedDomains` and `memory` are flagged in
  favour of `caps.network.allow` / `agentContext` — an *intermediate* v2 draft spelling that the
  published v2 grammar has since renamed again. Don't chase it.
- **`extends` is not resolved at load time.** `sbx kit inspect` shows it as a bare string; the CLI
  walks the parent chain at `sbx run` / `sbx create` time. Only a real run proves the kit inherits
  Claude Code's entrypoint and Anthropic credential.
- **For a `kind: sandbox` kit the positional agent argument is the kit's own `name`** (`turbo`), not
  `claude`.
- **Kit `ports` only ever get ephemeral host ports.** A pinned host port needs
  `sbx ports <sandbox> --publish` or `-p` at create time.
- **`files/` only recognises `files/home/` and `files/workspace/`.** Anything else is ignored with a
  warning. Copy with `cp -RL` — symlinks resolving outside the artifact root are rejected.
- **The sbx CLI runs kit commands without a daemon**, which is why CI can validate and push from a
  plain runner. The binary is in the `DockerSandboxes-linux-<arch>.tar.gz` release asset at
  `docker-sbx/sbx`.

## Commit conventions

- Conventional commits.
- `feat` is for changes to what consumers get — the kit spec, the image contents, published skills.
- Repo tooling (`.agents/skills/`, CI, docs) uses `chore` or `ci`.

## sbx CLI reference

**Authoritative docs** (consult before speculating):
- CLI reference: https://docs.docker.com/reference/cli/sbx/
- Sandboxes manual: https://docs.docker.com/ai/sandboxes/
- Kits: https://docs.docker.com/ai/sandboxes/customize/kits/
- Templates: https://docs.docker.com/ai/sandboxes/customize/templates/
- Network policy: https://docs.docker.com/reference/cli/sbx/policy/

Install: `brew install docker/tap/sbx`

The docs site renders client-side and is often useless to fetch. `sbx <command> --help` is the
fastest source of truth, and the release tarball gives you a runnable binary anywhere.

### Templates vs kits

They compose, they are not alternatives:

- A **template** is an image. It customises an existing agent's *environment*. Built with a
  Dockerfile (`docker build --push`) or captured with `sbx template save`.
- A **kit** is declarative config in `spec.yaml` — network, ports, credentials, install/startup
  hooks, files, agent memory.

A `kind: sandbox` kit carries its own `sandbox.image`, so the kit can supply the template itself —
one `--kit` flag instead of `--template` plus `--kit`. That's what Turbo does.

### `sbx run`

```
sbx run [flags] [AGENT] [PATH...] [-- AGENT_ARGS...]
```

Agents: `claude`, `codex`, `copilot`, `cursor`, `docker-agent`, `droid`, `gemini`, `kiro`,
`opencode`, `shell` — or a `kind: sandbox` kit's `name`.

| Flag | Purpose |
|---|---|
| `--kit` | Kit reference (directory, ZIP, or OCI). Repeatable; order is composition order |
| `-t, --template` | Container image override |
| `--name` | Sandbox name (default `<agent>-<workdir>`). **Re-attach with `sbx run --name <sandbox>`** — the agent positional is read from the sandbox spec |
| `-p, --publish` | Publish a port at creation time; ignored when re-attaching (use `sbx ports`) |
| `--clone` | Run against a private in-container clone rather than the bind mount |
| `-m, --memory` / `--cpus` | Resource limits |
| `-d, --detached` | Print the sandbox ID and exit without attaching |

Extra positional paths mount additional workspaces at the same absolute path; append `:ro` for
read-only.

### Other commands

| Command | Notes |
|---|---|
| `sbx create` | Same flags as `run`, without attaching |
| `sbx exec [-it] SANDBOX CMD` | Run a command inside a sandbox |
| `sbx ls [-q]` | List sandboxes |
| `sbx stop` / `sbx rm [--all]` | Lifecycle |
| `sbx ports SANDBOX [--publish/--unpublish SPEC]` | Spec: `[[HOST_IP:]HOST_PORT:]SANDBOX_PORT[/PROTO]`. Services must bind `0.0.0.0` |
| `sbx cp` | Copy files between host and sandbox |
| `sbx template ls/save/rm/load` | Template snapshots (this is `sbx save` in older docs) |
| `sbx skills import` | Seed the shared skills store; `--no-share-skills` opts a sandbox out |
| `sbx policy ls` / `policy log SANDBOX` | Active rules, and what the proxy actually allowed or blocked |
| `sbx diagnose` | Diagnose installation issues |
| `sbx tui` | Interactive dashboard |

### Network policy

```bash
sbx policy allow network --sandbox <sandbox> api.example.com:443   # scoped to one sandbox
sbx policy allow network "*.npmjs.org"                             # ALL sandboxes — global
sbx policy log <sandbox>                                           # what was blocked
```

Without `--sandbox` the rule is **global**. Kit `network.allowedDomains` rules are per-sandbox and
show up in `sbx policy ls` with provenance `kit` (since v0.29.0). Deny always beats allow.

Kit sources are themselves allowlisted and default to `docker.io/` only (v0.34.0+); other registries
and `git+` URLs need `sbx settings set kit.allowedSources`.

### Secrets

Secrets are **not** env vars in the VM — the host proxy injects them as HTTP auth headers, so the
raw value never enters the sandbox.

- Proxy-injected services: `anthropic`, `aws`, `github`, `google`, `groq`, `mistral`, `nebius`,
  `openai`, `xai`
- `sbx secret set -g <service> -t <token>` — global, applies at sandbox creation
- `sbx secret set <sandbox> <service> -t <token>` — per-sandbox, live

GitHub auth works out of the box via the proxy; the image deliberately carries no credential helper.

### Host access

1. `host.docker.internal` resolves to the host (the proxy translates it to `localhost`)
2. Allowlist the port: `sbx policy allow network localhost:11434`
3. `curl http://host.docker.internal:11434/...`

There is no native `/etc/hosts` support, and kit `files/` cannot write outside the agent home or
workspace. Custom hostnames (Herd/Valet `myapp.test`) need `sbx exec sudo tee -a /etc/hosts`, a
baked template, or `host.docker.internal` plus a `Host:` header.

## Image notes

- sbx uses a separate Docker daemon that does **not** share the local image store — templates must
  be pulled from a registry.
- The apt `chromium-browser` package on ARM64 Ubuntu is a non-functional snap stub. Chromium comes
  from Playwright into `/opt/chromium`, symlinked to `/usr/local/bin/chromium`, with
  `AGENT_BROWSER_EXECUTABLE_PATH` set.
- After changing the Dockerfile, verify agent-browser still works:
  ```bash
  docker run --rm --user agent <image> agent-browser batch "open file:///dev/null" "screenshot"
  ```
  Building needs `cdn.playwright.dev` and `playwright.download.prss.microsoft.com` reachable.
