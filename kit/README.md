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

You only need the long reference when **creating** the sandbox. After that the agent is read from
the sandbox's own spec:

```bash
sbx run --name <sandbox-name>
```

The current digest is in the [latest release](https://github.com/springloadedco/turbo/releases).

## Developing the kit

Local directory references are exempt from both the pinning rule and the kit source allowlist, so
iterate against the working copy:

```bash
sbx kit validate ./kit/
sbx kit inspect ./kit/ --json | jq '.manifest, .caps, .warnings'
sbx kit pack ./kit/ -o /tmp/turbo-kit.zip
sbx run --kit ./kit --name turbo-probe turbo
sbx exec turbo-probe -- php -v
sbx rm turbo-probe
```

`extends` stays an unresolved string in `inspect` output — the CLI walks the parent chain at
`sbx run` / `sbx create` time, not at load time. So `inspect` will not show the inherited Claude
entrypoint or Anthropic credential; only an actual `sbx run` proves the inheritance.

### Why `schemaVersion: "1"`

The current v2 grammar does not load on sbx v0.37.1 — strict decoding rejects it outright:

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
sbx policy allow network --sandbox <sandbox> example.com:443
```

Without `--sandbox` the rule applies globally to every sandbox on the machine.

To find what a failing command actually reached for:

```bash
sbx policy log <sandbox>
```
