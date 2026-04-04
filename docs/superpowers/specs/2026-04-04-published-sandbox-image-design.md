# Published Sandbox Image — Design Spec

**Date:** 2026-04-04
**Status:** Approved

## Background

The `sbx` CLI (Docker Sandboxes) uses a daemon that is completely separate from the host Docker daemon. It pulls templates exclusively from OCI registries — the local Docker image store is not shared. This means every user of `turbo:install` must push a custom image to a registry before they can create a sandbox.

Publishing `springloadedco/turbo` to Docker Hub eliminates this friction: users can reference the published image directly, and teams who need extras can extend it with their own Dockerfile.

## Image

**Registry:** `docker.io/springloadedco/turbo`

**Tags:**
| Tag | PHP Version |
|-----|-------------|
| `php8.3` | PHP 8.3 |
| `php8.4` | PHP 8.4 |
| `php8.5` | PHP 8.5 |
| `latest` | Alias for `php8.5` |

**Base:** `docker/sandbox-templates:claude-code` (Docker's official Claude Code sandbox base)

## Dockerfile Changes

Add `ARG PHP_VERSION=8.4` and add the `ondrej/php` PPA to support multiple PHP versions from a single Dockerfile:

```dockerfile
FROM docker/sandbox-templates:claude-code
ARG PHP_VERSION=8.4
USER root

RUN apt-get update \
  && apt-get install -y software-properties-common \
  && add-apt-repository ppa:ondrej/php \
  && apt-get update \
  && apt-get install -y --no-install-recommends \
    php${PHP_VERSION}-cli php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-mysql \
    unzip ca-certificates chromium-browser \
  && rm -rf /var/lib/apt/lists/*

# Node.js 22, Composer, agent-browser, git credential helper,
# npm wrapper, setup/fix scripts — unchanged from current Dockerfile
```

## CI/CD Pipeline

**File:** `.github/workflows/publish-sandbox.yml`

Triggered on push to `main`. Matrix builds all three PHP versions in parallel and pushes to Docker Hub. `php8.5` is also tagged `latest`.

```yaml
jobs:
  build:
    strategy:
      matrix:
        php: ['8.3', '8.4', '8.5']
    steps:
      - uses: docker/login-action
        with:
          username: ${{ secrets.DOCKERHUB_USERNAME }}
          password: ${{ secrets.DOCKERHUB_TOKEN }}
      - uses: docker/build-push-action
        with:
          push: true
          build-args: PHP_VERSION=${{ matrix.php }}
          tags: |
            springloadedco/turbo:php${{ matrix.php }}
            ${{ matrix.php == '8.5' && 'springloadedco/turbo:latest' || '' }}
```

**Secrets required in repo settings:** `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`

## Renovate Config

Renovate watches `docker/sandbox-templates:claude-code` and opens a PR when it updates. Automerge is off — a human reviews and merges, which triggers CI to rebuild and push fresh tags.

```json
{
  "packageRules": [{
    "matchDatasources": ["docker"],
    "matchPackageNames": ["docker/sandbox-templates"],
    "automerge": false
  }]
}
```

## Turbo Package Changes

### `config/turbo.php`

Default image changes to the published image:

```php
'image' => env('TURBO_DOCKER_IMAGE', 'docker.io/springloadedco/turbo:php8.4'),
```

### `turbo:install` prompt

Default image name becomes `docker.io/springloadedco/turbo:php8.4`. Hint text explains the two paths:

- **Use as-is:** reference the published image, no build needed
- **Extend:** write a `FROM springloadedco/turbo:php8.4` Dockerfile, set your own registry image in config, run `turbo:build`

### `turbo:build`

No changes needed. It builds and pushes whatever image name is in config. Users using the published image unchanged never need to run it. Users extending it run it to push their custom image to their own registry.

## User Flows

### Flow A: Use the published image (most users)

```
turbo:install
  → prompted for image name
  → accepts default: docker.io/springloadedco/turbo:php8.4
  → skips turbo:build (no custom Dockerfile)
  → sbx create --template docker.io/springloadedco/turbo:php8.4
  → sbx pulls from Docker Hub ✓
```

### Flow B: Extend the image (teams with custom needs)

```dockerfile
# Dockerfile in project root
FROM springloadedco/turbo:php8.4
USER root
RUN apt-get install -y redis-tools wkhtmltopdf
USER agent
```

```
turbo:install
  → enter custom image: docker.io/my-org/my-sandbox:latest
  → turbo:build → docker build --push -t docker.io/my-org/my-sandbox:latest
  → sbx create --template docker.io/my-org/my-sandbox:latest ✓
```

## Documentation Updates

README additions:
1. Available tags and how to reference them
2. Extending the image pattern with Dockerfile example
3. Note that `turbo:build` is only needed when using a custom Dockerfile
