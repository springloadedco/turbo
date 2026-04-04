# Published Sandbox Image — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish `springloadedco/turbo` to Docker Hub with PHP version tags so sbx can pull sandbox templates from a registry instead of requiring users to push their own images.

**Architecture:** Parameterize the existing Dockerfile with a `PHP_VERSION` build arg and the `ondrej/php` PPA. A GitHub Actions workflow builds the matrix (php8.3, php8.4, php8.5) and pushes to Docker Hub on merge to main. Renovate watches the base image for updates. The Turbo package defaults to the published image and only requires `turbo:build` for teams extending it. The install flow detects whether the user is using the published image (skip build) or a custom image (build + push).

**Tech Stack:** PHP 8.4, Pest, Docker, GitHub Actions, Renovate

---

### Task 1: Parameterize the Dockerfile for Multiple PHP Versions

**Files:**
- Modify: `Dockerfile`

The current Dockerfile hardcodes `php-cli php-mbstring ...` which installs whatever PHP version Ubuntu ships. To support multiple PHP versions from one Dockerfile, add a build arg and the `ondrej/php` PPA.

- [ ] **Step 1: Add PHP_VERSION build arg and ondrej/php PPA**

Replace the current PHP install block in `Dockerfile` (lines 1–8):

```dockerfile
FROM docker/sandbox-templates:claude-code
ARG PHP_VERSION=8.4

USER root

RUN apt-get update \
  && apt-get install -y --no-install-recommends software-properties-common \
  && add-apt-repository ppa:ondrej/php \
  && apt-get update \
  && apt-get install -y --no-install-recommends \
    php${PHP_VERSION}-cli php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-mysql \
    unzip ca-certificates chromium-browser \
  && rm -rf /var/lib/apt/lists/*
```

Everything below line 8 (Node.js 22, Composer, agent-browser, git credential helper, npm wrapper, COPY scripts, USER agent) stays unchanged.

- [ ] **Step 2: Verify the Dockerfile builds locally**

Run: `docker build --build-arg PHP_VERSION=8.4 -t turbo-test:php8.4 -f Dockerfile .`
Expected: Build succeeds. Then verify PHP version:
Run: `docker run --rm turbo-test:php8.4 php -v`
Expected: Output starts with `PHP 8.4`

- [ ] **Step 3: Verify a different PHP version builds**

Run: `docker build --build-arg PHP_VERSION=8.3 -t turbo-test:php8.3 -f Dockerfile .`
Run: `docker run --rm turbo-test:php8.3 php -v`
Expected: Output starts with `PHP 8.3`

- [ ] **Step 4: Commit**

```bash
git add Dockerfile
git commit -m "feat: parameterize Dockerfile with PHP_VERSION build arg

Add ondrej/php PPA and PHP_VERSION ARG so a single Dockerfile can
produce images for PHP 8.3, 8.4, and 8.5."
```

---

### Task 2: Create the GitHub Actions Publish Workflow

**Files:**
- Create: `.github/workflows/publish-sandbox.yml`

This workflow builds all three PHP version tags in parallel and pushes to Docker Hub. It triggers on push to `main` when the Dockerfile or docker/ directory changes, and on manual dispatch for ad-hoc rebuilds.

- [ ] **Step 1: Create the workflow file**

Create `.github/workflows/publish-sandbox.yml`:

```yaml
name: publish-sandbox

on:
  push:
    branches: [main]
    paths:
      - 'Dockerfile'
      - 'docker/**'
      - '.github/workflows/publish-sandbox.yml'
  workflow_dispatch:

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  build:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    strategy:
      fail-fast: false
      matrix:
        php: ['8.3', '8.4', '8.5']
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to Docker Hub
        uses: docker/login-action@v3
        with:
          username: ${{ secrets.DOCKERHUB_USERNAME }}
          password: ${{ secrets.DOCKERHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v6
        with:
          context: .
          push: true
          build-args: PHP_VERSION=${{ matrix.php }}
          tags: |
            springloadedco/turbo:php${{ matrix.php }}
            ${{ matrix.php == '8.5' && 'springloadedco/turbo:latest' || '' }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/publish-sandbox.yml
git commit -m "ci: add publish-sandbox workflow for Docker Hub

Matrix builds springloadedco/turbo:php8.3, php8.4, php8.5 on push
to main. Uses GHA cache for fast rebuilds. php8.5 is tagged latest."
```

---

### Task 3: Add Renovate Config for Base Image Updates

**Files:**
- Create: `renovate.json`

Renovate watches `docker/sandbox-templates:claude-code` in the Dockerfile and opens a PR when it updates. Automerge is off — a human reviews, and merge triggers the publish workflow.

- [ ] **Step 1: Create renovate.json**

Create `renovate.json` at the repo root:

```json
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": ["config:recommended"],
  "packageRules": [
    {
      "matchDatasources": ["docker"],
      "matchPackageNames": ["docker/sandbox-templates"],
      "automerge": false
    }
  ]
}
```

- [ ] **Step 2: Commit**

```bash
git add renovate.json
git commit -m "chore: add Renovate config for base image updates

Watches docker/sandbox-templates in the Dockerfile and opens PRs
when it updates. Merge triggers publish-sandbox workflow."
```

---

### Task 4: Update Config Default to Published Image

**Files:**
- Modify: `config/turbo.php:22-36`

The config default should reference the published Docker Hub image. Users who extend the image will override this in their `.env`.

- [ ] **Step 1: Update the config default and comment**

In `config/turbo.php`, replace the image config block (lines 22–36) with:

```php
    'docker' => [
        /*
        |--------------------------------------------------------------------------
        | Image Name
        |--------------------------------------------------------------------------
        |
        | The fully-qualified OCI registry image passed to `sbx create --template`.
        | sbx pulls templates from registries — the local Docker image store is not
        | shared with the sbx daemon.
        |
        | The default uses the published springloadedco/turbo image from Docker Hub.
        | To extend the image, create a Dockerfile with
        |   FROM springloadedco/turbo:php8.4
        | set your own registry image here, and run turbo:build.
        |
        */
        'image' => env('TURBO_DOCKER_IMAGE', 'docker.io/springloadedco/turbo:php8.4'),
```

- [ ] **Step 2: Run tests to verify config loads**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass. The `it('uses static default image name')` test should still pass since it checks `config('turbo.docker.image')` matches `$sandbox->image`.

- [ ] **Step 3: Commit**

```bash
git add config/turbo.php
git commit -m "chore: default config to published Docker Hub image

Default image is now docker.io/springloadedco/turbo:php8.4. Users
extending the image override via TURBO_DOCKER_IMAGE env var."
```

---

### Task 5: Update Install Flow — Skip Build for Published Image

**Files:**
- Modify: `src/Commands/InstallCommand.php:406-516`

The install flow currently always calls `turbo:build`. With a published image, most users accept the default and skip the build entirely. Only users extending the image need to build + push.

- [ ] **Step 1: Update `configureDockerImage()` with published image default**

In `src/Commands/InstallCommand.php`, replace the `configureDockerImage()` method (around line 409):

```php
    protected function configureDockerImage(): void
    {
        $default = 'docker.io/springloadedco/turbo:php8.4';

        $image = text(
            label: 'Docker image name',
            hint: 'Press enter to use the published image. To extend it, enter your own registry image (e.g. docker.io/my-org/my-sandbox:latest).',
            default: $default,
            required: true,
        );

        $this->writeDockerImageToConfig($image);

        config(['turbo.docker.image' => $image]);
    }
```

- [ ] **Step 2: Add `isPublishedImage()` helper**

Add this method to `InstallCommand`, after `configureDockerImage()`:

```php
    /**
     * Check if the configured image is the published springloadedco/turbo image.
     *
     * When using the published image, turbo:build is not needed since sbx
     * pulls it directly from Docker Hub.
     */
    protected function isPublishedImage(): bool
    {
        $image = config('turbo.docker.image', '');

        return str_starts_with($image, 'docker.io/springloadedco/turbo:')
            || str_starts_with($image, 'springloadedco/turbo:');
    }
```

- [ ] **Step 3: Update `offerDockerSetup()` to skip build for published image**

Replace `offerDockerSetup()` (around line 450):

```php
    protected function offerDockerSetup(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $wantsDocker = confirm(
            label: 'Set up Docker sandbox?',
            default: true,
        );

        if (! $wantsDocker) {
            return;
        }

        // Configure image name
        $this->configureDockerImage();

        // Only build if using a custom (non-published) image
        if (! $this->isPublishedImage()) {
            $exitCode = $this->call('turbo:build');

            if ($exitCode !== self::SUCCESS) {
                return;
            }
        }

        // Create the sandbox
        $sandbox = app(DockerSandbox::class);

        if ($sandbox->sandboxExists()) {
            $rebuild = confirm(
                label: "Sandbox '{$sandbox->sandboxName()}' already exists. Rebuild it?",
                default: false,
            );

            if (! $rebuild) {
                return;
            }

            $this->info('Removing existing sandbox...');
            $removeProcess = $sandbox->removeProcess();
            $removeProcess->run();

            if (! $removeProcess->isSuccessful()) {
                $this->error('Failed to remove sandbox.');
                $this->line($removeProcess->getErrorOutput());

                return;
            }
        }

        $this->info('Creating sandbox...');
        $createProcess = $sandbox->createProcess();
        $createProcess->run();

        if (! $createProcess->isSuccessful()) {
            $this->error('Failed to create sandbox.');
            $this->line($createProcess->getErrorOutput());

            return;
        }

        // Install plugins
        $this->installSandboxPlugins($sandbox);
    }
```

- [ ] **Step 4: Run tests**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/InstallCommand.php
git commit -m "feat: skip turbo:build when using published image

When the default springloadedco/turbo image is selected, the install
flow skips the build step since sbx pulls it directly from Docker Hub.
Custom images still trigger turbo:build."
```

---

### Task 6: Update Build Command Description

**Files:**
- Modify: `src/Commands/DockerBuildCommand.php:14-16`

The command description should clarify it's for custom images only, since the published image doesn't need building.

- [ ] **Step 1: Update the description**

In `src/Commands/DockerBuildCommand.php`, update line 16:

```php
    protected $description = 'Build and push a custom Docker sandbox image';
```

- [ ] **Step 2: Commit**

```bash
git add src/Commands/DockerBuildCommand.php
git commit -m "chore: update turbo:build description for custom images"
```

---

### Task 7: Update Unit Tests

**Files:**
- Modify: `tests/Unit/DockerSandboxTest.php:90-103`

The `buildProcess` test needs to assert `--push` is present in the command.

- [ ] **Step 1: Update the build process test**

In `tests/Unit/DockerSandboxTest.php`, replace the test at line 90:

```php
it('creates a build process with correct command', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $commandLine = $process->getCommandLine();
    expect($commandLine)
        ->toContain('docker')
        ->toContain('build')
        ->toContain('--progress=quiet')
        ->toContain('--push')
        ->toContain('-t')
        ->toContain('turbo')
        ->toContain('-f')
        ->toContain('Dockerfile');
});
```

- [ ] **Step 2: Run tests**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/DockerSandboxTest.php
git commit -m "test: assert --push flag in buildProcess command"
```

---

### Task 8: Update Integration Test

**Files:**
- Modify: `tests/Integration/DockerSandboxTest.php`

The integration test currently runs the actual `docker build`. With `--push`, this would try to push to a registry. Gate it behind an additional env var and update the output assertions.

- [ ] **Step 1: Update the integration test**

Replace the contents of `tests/Integration/DockerSandboxTest.php`:

```php
<?php

use Springloaded\Turbo\Services\DockerSandbox;
use Symfony\Component\Process\Process;

it('can build and push the sandbox image', function () {
    $sandbox = app(DockerSandbox::class);
    $process = $sandbox->buildProcess();

    $process->run();

    expect($process->isSuccessful())->toBeTrue();
})->skip(
    ! dockerIsAvailable() || ! getenv('RUN_DOCKER_PUSH_TESTS'),
    'Docker push tests require RUN_DOCKER_PUSH_TESTS=1 and registry auth'
);

function dockerIsAvailable(): bool
{
    $process = new Process(['docker', 'info']);
    $process->run();

    return $process->isSuccessful();
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/Integration/DockerSandboxTest.php
git commit -m "test: gate integration test behind RUN_DOCKER_PUSH_TESTS

The build process now pushes to a registry, so the integration test
requires registry auth. Renamed env var to RUN_DOCKER_PUSH_TESTS."
```

---

### Task 9: Update README

**Files:**
- Modify: `README.md:136-170`

Update the Docker sandbox documentation to reflect the published image, the new config default, and the extend pattern.

- [ ] **Step 1: Update the config example in README**

In `README.md`, replace the Docker config block (around lines 136–150):

```markdown
The config also includes Docker sandbox settings:

```php
'docker' => [
    'image'      => env('TURBO_DOCKER_IMAGE', 'docker.io/springloadedco/turbo:php8.4'),
    'dockerfile' => env('TURBO_DOCKER_DOCKERFILE'),
    'workspace'  => env('TURBO_DOCKER_WORKSPACE', base_path()),
],
```

| Key | Description | Default |
|-----|-------------|---------|
| `image` | Fully-qualified OCI registry image for the sandbox template | `docker.io/springloadedco/turbo:php8.4` |
| `dockerfile` | Path to a custom Dockerfile (falls back to the one shipped with Turbo) | `null` |
| `workspace` | Local directory mounted into the sandbox | `base_path()` |
```

- [ ] **Step 2: Update the Docker Sandbox section in README**

Replace the "Docker Sandbox" section (around lines 162–186):

```markdown
### Docker Sandbox

Turbo publishes a pre-built sandbox image to Docker Hub based on `docker/sandbox-templates:claude-code` with PHP, common extensions, Composer, Node.js 22, and Chromium pre-installed.

**Available tags:**

| Tag | PHP Version |
|-----|-------------|
| `springloadedco/turbo:php8.5` | PHP 8.5 (also `latest`) |
| `springloadedco/turbo:php8.4` | PHP 8.4 (default) |
| `springloadedco/turbo:php8.3` | PHP 8.3 |

Most users don't need to build anything — `turbo:install` uses the published image by default and sbx pulls it from Docker Hub.

**Extending the image:**

If your project needs additional tools, create a Dockerfile in your project root:

```dockerfile
FROM springloadedco/turbo:php8.4
USER root
RUN apt-get update && apt-get install -y redis-tools
USER agent
```

Set your own registry image in `.env`:

```
TURBO_DOCKER_IMAGE=docker.io/my-org/my-sandbox:latest
```

Then build and push:

```bash
php artisan turbo:build
```

**Start an interactive Claude session:**

```bash
php artisan turbo:claude
```

This opens an interactive Claude session inside the sandbox with your project workspace mounted.

**Run a one-off prompt:**

```bash
php artisan turbo:prompt "Write tests for the UserController"
```

Sends the prompt to Claude inside the sandbox and streams the output back to your terminal.
```

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: update README for published sandbox image

Document the springloadedco/turbo Docker Hub image, available PHP
tags, and the extend-and-push pattern for custom images."
```

---

### Task 10: Update CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

Update any references to the old image default and document the registry requirement.

- [ ] **Step 1: Search and update old image references**

In `CLAUDE.md`, find the "Docker Sandbox Patterns" section. Add a note under the sbx Commands subsection:

```markdown
#### Image Registry Requirement
- sbx uses a separate Docker daemon that does NOT share the local image store
- Templates must be pulled from an OCI registry (Docker Hub, GHCR, etc.)
- Default image: `docker.io/springloadedco/turbo:php8.4` — published via CI
- `turbo:build` is only needed for custom images extending the published one
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: add image registry requirement to CLAUDE.md"
```

---

### Task 11: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `cd /Users/sagalbot/Sites/turbo && composer test`
Expected: All tests pass.

- [ ] **Step 2: Run static analysis**

Run: `cd /Users/sagalbot/Sites/turbo && composer analyse`
Expected: No errors.

- [ ] **Step 3: Run formatter**

Run: `cd /Users/sagalbot/Sites/turbo && composer format`

- [ ] **Step 4: Verify Dockerfile builds locally with build arg**

Run: `docker build --build-arg PHP_VERSION=8.4 -t test-turbo:php8.4 -f Dockerfile .`
Expected: Builds successfully.

- [ ] **Step 5: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: apply formatting fixes"
```
