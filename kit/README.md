# Turbo kit

A [Docker Sandboxes](https://docs.docker.com/ai/sandboxes/) kit that gives Claude Code a
Laravel-ready sandbox: PHP 8.4 with the common extensions, Composer, Node 22, headless Chromium
and `agent-browser`.

It is a `kind: sandbox` kit that `extends: claude`, so it inherits Claude Code's entrypoint and
Anthropic OAuth credential handling and adds:

- `sandbox.image` — the template image published from this repo's `Dockerfile`
- `network.allowedDomains` — a per-sandbox egress allowlist for the toolchain
- `memory` — what the agent should know about this environment
- `files/home/.claude/skills/agent-browser/` — the agent-browser skill, no host-side install needed

## Usage

Because the kit is `kind: sandbox`, the positional agent argument is the kit's own name (`turbo`),
not `claude`:

```bash
sbx run --kit oci://docker.io/springloadedco/turbo-kit@sha256:<digest> turbo
```

You only need the long reference when **creating** the sandbox. After that:

```bash
sbx run <sandbox-name>
```

The current digest is in the [latest release](https://github.com/springloadedco/turbo/releases).

## Developing the kit

Local directory references are exempt from both the pinning rule and the kit source allowlist, so
iterate against the working copy:

```bash
sbx kit validate ./kit/
sbx kit inspect ./kit/ --output json | jq '.sandbox, .credentials, .warnings'
sbx run --kit ./kit --name turbo-probe turbo
sbx exec turbo-probe -- php -v
sbx rm turbo-probe
```

`schemaVersion` is `"1"`. A `"2"` spec hard-fails to load on any `sbx` predating v2 support, so v1
stays until the whole team is past that. `sbx kit inspect --output json | jq '.warnings'` lists the
legacy surfaces that will need migrating; the canonical model they normalise to is identical.

## Refreshing the vendored agent-browser skill

`files/home/.claude/skills/agent-browser/` is a copy of
[vercel-labs/agent-browser](https://github.com/vercel-labs/agent-browser) and will drift. Refresh it
as part of a release:

```bash
npx skills add vercel-labs/agent-browser --skill agent-browser -a claude-code -y
rm -rf kit/files/home/.claude/skills/agent-browser
cp -RL .agents/skills/agent-browser kit/files/home/.claude/skills/agent-browser
```

Copy with `-L` — the spec rejects symlinks that resolve outside the artifact root.

## Adding a domain

Anything every project needs belongs in `network.allowedDomains` here. Anything project-specific
belongs in a per-sandbox rule on the host:

```bash
sbx policy allow network <sandbox> example.com:443
```

To find what a failing command actually reached for:

```bash
sbx policy log <sandbox>
```
