![Turbo](turbo.png)

## What is Turbo?

Turbo is Springloaded's setup for AI-assisted Laravel development. It's two independent pieces:

- **A [Docker Sandboxes](https://docs.docker.com/ai/sandboxes/) kit** — one command gives you an
  isolated Claude Code sandbox with PHP 8.4, Composer, Node 22, headless Chromium and
  `agent-browser`, plus a per-sandbox network allowlist.
- **A skills library** — Springloaded's Laravel and GitHub conventions, installed per project with
  [`npx skills`](https://skills.sh) and usable by any agent that supports skills (Claude, Cursor,
  Codex, Copilot).

Use either without the other.

## The sandbox

### Prerequisites

- The sbx CLI — `brew install docker/tap/sbx`

### Getting started

```bash
cd your-laravel-project
sbx run --kit oci://docker.io/springloadedco/turbo-kit@sha256:<digest> turbo
```

Grab the current digest from the [latest release](https://github.com/springloadedco/turbo/releases).
Remote kit references must be pinned to a digest — sbx rejects tags.

Because the kit is `kind: sandbox`, the positional agent is the kit's own name (`turbo`), not
`claude`. You only need the long reference once, when the sandbox is created. After that:

```bash
sbx run --name <sandbox-name>
```

On the first run you'll be prompted to authenticate with Claude. That's once per sandbox.

### What's in it

| | |
|---|---|
| PHP | 8.4 with mbstring, xml, curl, zip, intl, bcmath, sqlite3, mysql, pgsql, gd, redis, imagick, memcached |
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

The kit points at `docker.io/springloadedco/turbo:latest`, built from this repo's `Dockerfile`. To
add tooling, either fork the kit or layer your own image on top:

```dockerfile
FROM springloadedco/turbo:latest
USER root
RUN apt-get update && apt-get install -y redis-tools
USER agent
```

```bash
docker build --push -t docker.io/my-org/my-sandbox:latest .
sbx run --kit oci://docker.io/springloadedco/turbo-kit@sha256:<digest> \
        --template docker.io/my-org/my-sandbox:latest turbo
```

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
sbx run --kit ./kit --name probe turbo   # smoke test the working copy
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
