![Turbo](turbo.png)

## What is Turbo?

Turbo is Springloaded's setup for AI-assisted Laravel development. It's two independent pieces:

- **A [Docker Sandboxes](https://docs.docker.com/ai/sandboxes/) kit** — one command gives you an
  isolated agent sandbox with PHP, Composer, Node 22, headless Chromium and `agent-browser`, plus a
  per-sandbox network allowlist. It's a mixin, so it layers onto **any** agent — Claude, Codex,
  Gemini, opencode.
- **A skills library** — Springloaded's Laravel and GitHub conventions, installed per project with
  [`npx skills`](https://skills.sh) and usable by any agent that supports skills (Claude, Cursor,
  Codex, Copilot).

Use either without the other.

## The sandbox

### Prerequisites

- The sbx CLI — `brew install docker/tap/sbx`

### Getting started

```bash
curl -fsSL https://raw.githubusercontent.com/springloadedco/turbo/main/install.sh | bash
```

That clones Turbo to `~/.turbo` and adds a `turbo` shell function. Then, from any project:

```bash
turbo                        # claude
turbo codex                  # or any other agent
turbo claude -- --continue   # anything after the agent goes to sbx run
turbo-update                 # pull the latest kit
```

On the first run you'll be prompted to authenticate with your agent. That's once per sandbox.

<details>
<summary>Without the installer</summary>

The function is only shorthand — the kit is an ordinary `--kit` reference:

```bash
git clone https://github.com/springloadedco/turbo ~/.turbo
sbx run --kit ~/.turbo/kit --template docker.io/springloadedco/turbo:latest claude
```

Or reference the published artifact instead of a checkout, using the digest from the
[latest release](https://github.com/springloadedco/turbo/releases) — remote kit references must be
pinned to a digest, since sbx rejects tags:

```bash
sbx run --kit oci://docker.io/springloadedco/turbo-kit@sha256:<digest> claude
```

Either way, the reference is only needed when the sandbox is created. After that, re-attach with
`sbx run --name <sandbox-name>`.

</details>

### Any agent

The kit is a `kind: mixin`, so it layers onto whichever agent you name — it installs the toolchain
rather than replacing the agent:

```bash
turbo claude
turbo codex
turbo gemini
```

`turbo claude` starts from the prebuilt `springloadedco/turbo` image, so the kit's install hooks
find everything already present and creation is near-instant. Other agents start from their own
stock base image and the kit installs the toolchain on first create — a few minutes, once. Every
install command is guarded on the binary it provides, so nothing is done twice.

To skip the prebuilt image and always install from the stock base, set `TURBO_TEMPLATE=`.

### What's in it

| | |
|---|---|
| PHP | Ubuntu's current `php-cli` (8.5 as of writing) with mbstring, xml, curl, zip, intl, bcmath, sqlite3, mysql, pgsql, gd, redis, imagick, memcached |
| Node | 22 (the base image ships 20) |
| Tooling | Composer, headless Chromium, [agent-browser](https://agent-browser.dev) |

The agent-browser skill ships inside the kit, so the agent knows how to drive the browser with no
host-side install.

### Network policy

The kit declares a **per-sandbox** allowlist covering Packagist, npm, GitHub, the Ubuntu archives,
the Playwright CDNs and the usual Laravel documentation hosts. It shows up in `sbx policy ls` with
provenance `kit`, scoped to that sandbox only.

To add something for one project:

```bash
sbx policy allow network --sandbox <sandbox> api.example.com:443
```

Without `--sandbox` the rule applies globally to every sandbox on your machine. To find out what a
failing command actually reached for:

```bash
sbx policy log <sandbox>
```

If a domain is needed by every project, add it to `kit/spec.yaml` instead and open a PR.

### Extending

`docker.io/springloadedco/turbo:latest`, built from this repo's `Dockerfile`, is an **optional
accelerator** — it bakes in what the kit would otherwise install. To add tooling, layer your own
image on top and point `TURBO_TEMPLATE` at it:

```dockerfile
FROM springloadedco/turbo:latest
USER root
RUN apt-get update && apt-get install -y redis-tools
USER agent
```

```bash
docker build --push -t docker.io/my-org/my-sandbox:latest .
TURBO_TEMPLATE=docker.io/my-org/my-sandbox:latest turbo claude
```

Note the image is built on the `claude-code` base, which is why the `turbo` function only applies it
for the `claude` agent.

See [`kit/README.md`](kit/README.md) for developing the kit itself.

## Skills

```bash
npx skills add springloadedco/turbo
```

Pick what you want — nothing is installed by default. Skills are grouped:

**Laravel** — Springloaded's conventions. Opinionated and framework-specific; take them only where
they fit.

| Skill | Description |
|-------|-------------|
| `laravel-controllers` | Invokable controller patterns with Inertia |
| `laravel-actions` | Business logic encapsulation patterns |
| `laravel-validation` | Form Request validation patterns |
| `laravel-testing` | Pest/PHPUnit testing best practices |
| `laravel-inertia` | TypeScript page component patterns |

**GitHub** — workflow conventions, framework-agnostic.

| Skill | Description |
|-------|-------------|
| `github-issue` | Atomic issue creation with verifiable acceptance criteria |
| `github-labels` | Consistent label taxonomy (type/priority) |
| `github-milestone` | Well-structured milestones grouping related issues |

Update them later with `npx skills update`.

### Superpowers

Turbo no longer installs [Superpowers](https://github.com/obra/superpowers) for you. Add it directly
when you want the `/brainstorming` → `/writing-plans` → `/executing-plans` workflow:

```bash
npx skills add obra/superpowers --skill '*'
```

## Development

```bash
sbx kit validate ./kit/                  # check the spec
sbx kit inspect ./kit/ --json | jq       # normalized form
sbx run --kit ./kit --name probe claude  # smoke test the working copy
sbx rm probe
```

Local directory references skip the digest-pinning rule, so `--kit ./kit` is the iteration loop.
`CLAUDE.md` covers the sbx behaviour worth not relearning; `kit/README.md` covers the kit itself.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jeff Sagal](https://github.com/sagalbot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
