# Turbo kit

A [Docker Sandboxes](https://docs.docker.com/ai/sandboxes/) kit that gives a sandbox agent a
Laravel toolchain: PHP with the common extensions, Composer, Node 22, headless Chromium and
`agent-browser`.

It is a **`kind: mixin`** with no `requires.agent`, so it layers onto any base agent:

```bash
sbx run --kit ~/.turbo/kit claude
sbx run --kit ~/.turbo/kit codex
sbx run --kit ~/.turbo/kit gemini
```

It contributes:

- `commands.install` — the toolchain, every step guarded on the binary it provides
- `network.allowedDomains` — a per-sandbox egress allowlist covering both install and runtime
- `environment.variables` — `PLAYWRIGHT_BROWSERS_PATH`, `AGENT_BROWSER_EXECUTABLE_PATH`
- `memory` — what the agent should know about this environment
- `files/home/` — the agent-browser skill

## Why a mixin, not a sandbox kit

A `kind: sandbox` kit can carry its own `sandbox.image`, which is one fewer flag — but it binds the
kit to a single agent through `extends:`, and the obvious fix does not work:

```
WARN: field "mixins" is accepted but not yet implemented:
      mixin composition is accepted in the schema but not yet applied by the runtime
```

So per-agent sandbox kits cannot share a common mixin on sbx v0.37.1 — the shared content would
have to be duplicated per agent, alongside one image per agent base. A mixin avoids all of that:
runtime `--kit` composition *is* implemented, so one artifact serves every agent.

The cost is that the mixin cannot name a template image. `docker.io/springloadedco/turbo:latest`
becomes an optional accelerator passed with `--template`, which the `turbo` shell function does
automatically for the `claude` agent (the image is built on the `claude-code` base).

## Guarded installs

Every install command exits early when its binary is already present:

```sh
command -v php >/dev/null && exit 0
```

On the prebuilt image all five guards no-op — measured at roughly one second total. On a stock
agent base they install the toolchain once. The same artifact is therefore both the fast path and
the portable one.

When adding an install step, guard it. An unguarded step runs on every sandbox creation, including
recreates.

## Developing the kit

Local directory references are exempt from both the pinning rule and the kit source allowlist, so
iterate against the working copy:

```bash
sbx kit validate ./kit/
sbx kit inspect ./kit/ --json | jq '.manifest, .caps, .warnings'
sbx kit pack ./kit/ -o /tmp/turbo-kit.zip

sbx run --kit ./kit --name turbo-probe claude
sbx exec turbo-probe -- php -v
sbx rm turbo-probe
```

### Why `schemaVersion: "1"`

The current v2 grammar does not load on sbx v0.37.1 — strict decoding rejects it:

```
INVALID: artifact: invalid spec.yaml: yaml: unmarshal errors:
  line 7: field permissions not found in type spec.SpecFile
  line 11: field agentInstructions not found in type spec.SpecFile
```

v1 loads everywhere and normalises to the same canonical model. `validate` reports two expected
deprecation warnings:

```
WARN: deprecated field "network.allowedDomains": use 'caps.network.allow' instead (kit-spec v2)
WARN: deprecated field "memory": use 'agentContext' instead (kit-spec v2)
```

Those point at an intermediate v2 draft spelling (`caps` / `agentContext`), which the published v2
grammar has since renamed again to `permissions` / `agentInstructions`. Don't chase it — stay on v1
until a `schemaVersion: "2"` spec validates on the sbx everyone is running.

## The vendored agent-browser skill

`files/home/` ships [vercel-labs/agent-browser](https://github.com/vercel-labs/agent-browser)'s
skill to **two** paths, because agents disagree on where skills live:

- `.claude/skills/agent-browser/` — Claude Code
- `.agents/skills/agent-browser/` — the cross-agent convention used by most others

It will drift from upstream. Refresh it as part of a release:

```bash
npx skills add vercel-labs/agent-browser --skill agent-browser -a claude-code -y
for target in .claude .agents; do
  rm -rf "kit/files/home/$target/skills/agent-browser"
  cp -RL .agents/skills/agent-browser "kit/files/home/$target/skills/agent-browser"
done
```

Copy with `-L` — the spec rejects symlinks that resolve outside the artifact root.

## Adding a domain

Anything every project needs belongs in `network.allowedDomains` here. Anything project-specific
belongs in a per-sandbox rule on the host:

```bash
sbx policy allow network --sandbox <sandbox> example.com:443
```

Without `--sandbox` the rule applies globally to every sandbox on the machine.

To find what a failing command actually reached for:

```bash
sbx policy log <sandbox>
```

Remember the allowlist now has to cover **install time** as well as runtime — `apt-get update`
refreshes every configured source, and `n` fetches Node from `nodejs.org`.
