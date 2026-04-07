<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Springloaded\Turbo\Commands\Concerns\ProcessesSkills;
use Springloaded\Turbo\Services\DockerSandbox;
use Springloaded\Turbo\Services\FeedbackLoopDetector;
use Springloaded\Turbo\Services\SkillsService;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    use ProcessesSkills;

    public $signature = 'turbo:install';

    public $description = 'Set up Turbo for your project';

    /**
     * Skill groups offered during install, in display order.
     *
     * - `prefix` is the label shown in the multiselect (padded for alignment).
     * - `skills` lists turbo-bundled skills (strings) or third-party skills (arrays with name/source).
     * - `defaultEnabled` controls whether the group is pre-checked on a fresh install.
     *
     * @var array<string, array{prefix: string, skills: array<string|array{name: string, source: string, skill?: string}>, defaultEnabled: bool}>
     */
    protected array $skillGroups = [
        'laravel' => [
            'prefix' => 'Laravel',
            'skills' => ['laravel-controllers', 'laravel-actions', 'laravel-validation', 'laravel-testing', 'laravel-inertia'],
            'defaultEnabled' => true,
        ],
        'project' => [
            'prefix' => 'Project',
            'skills' => ['feedback-loops', 'agent-captures'],
            'defaultEnabled' => true,
        ],
        'github' => [
            'prefix' => 'GitHub',
            'skills' => ['github-issue', 'github-labels', 'github-milestone'],
            'defaultEnabled' => false,
        ],
        'thirdParty' => [
            'prefix' => '3rd-party',
            'skills' => [
                ['name' => 'superpowers', 'source' => 'obra/superpowers', 'skill' => '*'],
                ['name' => 'agent-browser', 'source' => 'vercel-labs/agent-browser'],
            ],
            'defaultEnabled' => true,
        ],
    ];

    public function __construct(
        protected SkillsService $skills,
        protected Filesystem $files,
        protected FeedbackLoopDetector $detector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'turbo-config',
        ]);

        $this->configureFeedbackLoops();

        if (! $this->checkNpxAvailable()) {
            $this->error('npx is required to install skills. Please install Node.js and npm first.');
            $this->line('See: https://nodejs.org/');

            return self::FAILURE;
        }

        $selectedSkills = $this->selectSkills();

        if (empty($selectedSkills['turbo']) && empty($selectedSkills['thirdParty'])) {
            $this->info('No skills selected.');

            return self::SUCCESS;
        }

        $selectedAgents = $this->selectAgents();

        if (empty($selectedAgents)) {
            $this->info('No agents selected.');

            return self::SUCCESS;
        }
        if (! empty($selectedSkills['turbo'])) {
            $this->info('Installing Turbo skills...');

            $exitCode = $this->runNpxSkillsAdd(
                $this->skills->getSkillsSourcePath(),
                $selectedSkills['turbo'],
                $selectedAgents
            );

            if ($exitCode !== 0) {
                $this->error('Failed to install Turbo skills.');

                return self::FAILURE;
            }

            // Process templates
            $this->processInstalledSkills();
        }

        foreach ($selectedSkills['thirdParty'] as $skill) {
            $this->info("Installing {$skill['name']} skill...");

            $exitCode = $this->runNpxSkillsAdd(
                $skill['source'],
                [$skill['skill'] ?? $skill['name']],
                $selectedAgents
            );

            if ($exitCode !== 0) {
                $this->error("Failed to install {$skill['name']}.");

                return self::FAILURE;
            }
        }

        // Step 4: Configure secrets (GitHub token)
        $this->configureSecrets();

        // Step 5: Docker sandbox
        $this->offerDockerSetup();

        $this->newLine();
        $this->info('Turbo installation complete!');

        return self::SUCCESS;
    }

    /**
     * Detect feedback loops and optionally let the user confirm them.
     */
    protected function configureFeedbackLoops(): void
    {
        $detected = $this->detector->detect();

        if (empty($detected)) {
            return;
        }

        if ($this->input->isInteractive()) {
            $options = [];
            foreach ($detected as $cmd) {
                $options[$cmd] = $cmd;
            }

            $detected = multiselect(
                label: 'Which feedback loops should be injected into skills?',
                options: $options,
                default: array_keys($options),
            );
        }

        if (empty($detected)) {
            return;
        }

        $this->writeFeedbackLoopsToConfig($detected);
    }

    /**
     * Write the selected feedback loop commands into the published config file.
     *
     * @param  array<string>  $commands
     */
    protected function writeFeedbackLoopsToConfig(array $commands): void
    {
        $configPath = config_path('turbo.php');

        if (! $this->files->exists($configPath)) {
            return;
        }

        $content = $this->files->get($configPath);

        $replacement = "'feedback_loops' => [\n";
        foreach ($commands as $cmd) {
            $replacement .= "        '{$cmd}',\n";
        }
        $replacement .= '    ]';

        $content = preg_replace(
            "/'feedback_loops'\s*=>\s*\[[^\]]*\]/s",
            $replacement,
            $content
        );

        $this->files->put($configPath, $content);

        config(['turbo.feedback_loops' => $commands]);
    }

    /**
     * Prompt user to select skills via a flat multiselect with grouped labels.
     *
     * Options are labeled `Group › skill-name` (padded), with already-installed
     * skills marked `(installed)` and smart per-group defaults.
     *
     * @return array{turbo: array<string>, thirdParty: array<array{name: string, source: string, skill?: string}>}
     */
    protected function selectSkills(): array
    {
        $installed = $this->skills->getInstalledSkillNames();
        $flat = $this->flattenSkills();

        if (! $this->input->isInteractive()) {
            $selected = $this->defaultSelectedKeys($flat, $installed);

            return $this->partitionByKeys($flat, $selected);
        }

        $options = [];
        $defaults = $this->defaultSelectedKeys($flat, $installed);
        $prefixWidth = $this->prefixWidth();

        foreach ($flat as $key => $entry) {
            $prefix = str_pad($entry['prefix'], $prefixWidth);
            $name = $entry['name'];
            $suffix = in_array($name, $installed, true) ? ' (installed)' : '';
            $options[$key] = "{$prefix} › {$name}{$suffix}";
        }

        $selected = multiselect(
            label: 'Which skills would you like to install?',
            options: $options,
            default: $defaults,
            scroll: 15,
        );

        return $this->partitionByKeys($flat, $selected);
    }

    /**
     * Flatten skillGroups into a single indexed list with group metadata.
     *
     * Returns a map of unique keys (e.g. 'laravel:laravel-controllers') to
     * entries describing each skill's group context.
     *
     * @return array<string, array{prefix: string, group: string, name: string, definition: string|array{name: string, source: string, skill?: string}}>
     */
    protected function flattenSkills(): array
    {
        $flat = [];

        foreach ($this->skillGroups as $groupKey => $group) {
            foreach ($group['skills'] as $skill) {
                $name = $this->skillName($skill);
                $flat["{$groupKey}:{$name}"] = [
                    'prefix' => $group['prefix'],
                    'group' => $groupKey,
                    'name' => $name,
                    'definition' => $skill,
                ];
            }
        }

        return $flat;
    }

    /**
     * Compute default-selected skill keys.
     *
     * A skill is default-checked if it's already installed, or if its group
     * is default-enabled.
     *
     * @param  array<string, array{prefix: string, group: string, name: string, definition: string|array{name: string, source: string, skill?: string}}>  $flat
     * @param  array<string>  $installed
     * @return array<string>
     */
    protected function defaultSelectedKeys(array $flat, array $installed): array
    {
        // Determine which groups already have at least one installed skill.
        $groupHasInstalled = [];
        foreach ($flat as $entry) {
            if (in_array($entry['name'], $installed, true)) {
                $groupHasInstalled[$entry['group']] = true;
            }
        }

        $defaults = [];

        foreach ($flat as $key => $entry) {
            $group = $this->skillGroups[$entry['group']];
            $isInstalled = in_array($entry['name'], $installed, true);

            // On first install (nothing installed in this group), use defaultEnabled.
            // On re-runs, only preselect what's actually installed.
            $isFirstInstall = ! isset($groupHasInstalled[$entry['group']]);

            if ($isInstalled || ($isFirstInstall && $group['defaultEnabled'])) {
                $defaults[] = $key;
            }
        }

        return $defaults;
    }

    /**
     * Partition selected keys back into turbo + thirdParty buckets.
     *
     * @param  array<string, array{prefix: string, group: string, name: string, definition: string|array{name: string, source: string, skill?: string}}>  $flat
     * @param  array<string>  $selected
     * @return array{turbo: array<string>, thirdParty: array<array{name: string, source: string, skill?: string}>}
     */
    protected function partitionByKeys(array $flat, array $selected): array
    {
        $turbo = [];
        $thirdParty = [];

        foreach ($selected as $key) {
            if (! isset($flat[$key])) {
                continue;
            }

            $definition = $flat[$key]['definition'];

            if (is_array($definition)) {
                $thirdParty[] = $definition;
            } else {
                $turbo[] = $definition;
            }
        }

        return ['turbo' => $turbo, 'thirdParty' => $thirdParty];
    }

    /**
     * Width of the longest group prefix, for padding labels.
     */
    protected function prefixWidth(): int
    {
        return max(array_map(
            fn (array $group): int => mb_strlen($group['prefix']),
            $this->skillGroups,
        ));
    }

    /**
     * Extract the skill name from a string or definition array.
     *
     * @param  string|array{name: string, source: string, skill?: string}  $skill
     */
    protected function skillName(string|array $skill): string
    {
        return is_array($skill) ? $skill['name'] : $skill;
    }

    /**
     * Prompt user to select which agents to install skills to.
     *
     * @return array<string>
     */
    protected function selectAgents(): array
    {
        $agents = $this->skills->getAgentChoices();

        if ($this->input->isInteractive()) {
            return multiselect(
                label: 'Which agents should skills be installed to?',
                options: $agents,
                default: array_keys($agents),
            );
        }

        return array_keys($agents);
    }

    /**
     * Run npx skills add non-interactively with pre-selected skills and agents.
     *
     * @param  array<string>  $skills
     * @param  array<string>  $agents
     */
    protected function runNpxSkillsAdd(string $source, array $skills, array $agents): int
    {
        $command = ['npx', 'skills', 'add', $source];

        foreach ($skills as $skill) {
            $command[] = '--skill';
            $command[] = $skill;
        }

        foreach ($agents as $agent) {
            $command[] = '--agent';
            $command[] = $agent;
        }

        $command[] = '-y';

        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());
        }

        return $process->getExitCode();
    }

    /**
     * Configure secrets: optional GitHub token.
     *
     * Claude authentication is handled inside the sandbox via setup-token
     * during Docker setup, not on the host.
     */
    protected function configureSecrets(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $this->configureGitHubToken();
    }

    /**
     * Prompt for optional GitHub token and write to settings.
     */
    protected function configureGitHubToken(): void
    {
        $settingsPath = base_path('.claude/settings.local.json');
        $settings = $this->files->exists($settingsPath)
            ? json_decode($this->files->get($settingsPath), true) ?? []
            : [];

        $existingToken = $settings['env']['GH_TOKEN'] ?? null;

        if ($existingToken) {
            $masked = substr($existingToken, 0, 4).'...'.substr($existingToken, -4);

            $replace = confirm(
                label: "GitHub token already configured ({$masked}). Replace it?",
                default: false,
            );

            if (! $replace) {
                return;
            }
        } else {
            $wantsToken = confirm(
                label: 'Configure gh CLI access for Claude? (recommended)',
                hint: 'A GitHub token allows Claude to create issues, pull requests, and manage workflows via the gh CLI.',
                default: true,
            );

            if (! $wantsToken) {
                return;
            }
        }

        $sandboxName = 'claude-'.Str::slug(basename(base_path()));
        $tokenUrl = 'https://github.com/settings/personal-access-tokens/new?name='.urlencode('turbo-'.$sandboxName).'&description=Turbo+%28Claude+gh+CLI%29&contents=write&issues=write&pull_requests=write&workflows=write&actions=write';

        $this->line("  Generate a token here: <href=$tokenUrl>$tokenUrl</>");
        $this->newLine();

        $token = text(
            label: 'Paste your GitHub token',
            required: true,
        );

        if (empty($token)) {
            return;
        }

        $settings['env'] = array_merge($settings['env'] ?? [], [
            'GH_TOKEN' => $token,
        ]);

        $this->writeSettings($settingsPath, $settings);
        $this->info('GitHub token configured.');

        if ($this->sbxAvailable()) {
            $sandbox = app(DockerSandbox::class);
            $process = new Process(['sbx', 'secret', 'set', $sandbox->sandboxName(), 'github', '-t', $token]);
            $process->run();

            if ($process->isSuccessful()) {
                $this->info('GitHub token set as sandbox secret.');
            }
        }
    }

    /**
     * Write settings to .claude/settings.local.json.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function writeSettings(string $path, array $settings): void
    {
        $dir = dirname($path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        $this->files->put(
            $path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->addToGitignore();
    }

    /**
     * Add settings.local.json to .gitignore if not already present.
     */
    protected function addToGitignore(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! $this->files->exists($gitignorePath)) {
            return;
        }

        $contents = $this->files->get($gitignorePath);
        $pattern = '.claude/settings.local.json';

        if (str_contains($contents, $pattern)) {
            return;
        }

        $addition = "\n# Claude local settings (contains secrets)\n{$pattern}\n";
        $this->files->append($gitignorePath, $addition);

        $this->info('Added .claude/settings.local.json to '.$gitignorePath);
    }

    /**
     * Prompt for the Docker image name and write it to the config file.
     */
    protected function configureDockerImage(): void
    {
        $default = 'docker.io/springloadedco/turbo:latest';

        $this->newLine();
        $this->line('The default image includes PHP 8.4, Composer, Node 22, Chromium,');
        $this->line('and fixes for native binary corruption during npm install.');
        $this->line('To customize, extend with <comment>FROM springloadedco/turbo:latest</comment>');
        $this->line('and push to a registry.');
        $this->newLine();

        $image = text(
            label: 'Docker image',
            default: $default,
            required: true,
        );

        $this->writeDockerImageToConfig($image);

        config(['turbo.docker.image' => $image]);
    }

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

    /**
     * Write the Docker image name into the published config file.
     */
    protected function writeDockerImageToConfig(string $image): void
    {
        $configPath = config_path('turbo.php');

        if (! $this->files->exists($configPath)) {
            return;
        }

        $content = $this->files->get($configPath);

        $content = preg_replace(
            "/'image'\s*=>\s*env\([^)]+\)/",
            "'image' => env('TURBO_DOCKER_IMAGE', '{$image}')",
            $content
        );

        $this->files->put($configPath, $content);
    }

    /**
     * Set up the Docker sandbox if sbx is available and no sandbox exists yet.
     *
     * Skips with an informative message when sbx is not installed or when a
     * sandbox for this project already exists.
     */
    protected function offerDockerSetup(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        if (! $this->sbxAvailable()) {
            $this->newLine();
            $this->line('Note: sbx CLI not installed. Skipping Docker sandbox.');
            $this->line('  Install with: brew install docker/tap/sbx');

            return;
        }

        $sandbox = app(DockerSandbox::class);

        if ($sandbox->sandboxExists()) {
            $this->newLine();
            $this->info("✓ Sandbox '{$sandbox->sandboxName()}' already exists.");
            $this->line('  Run `php artisan turbo:doctor` to verify state.');

            return;
        }

        $this->configureDockerImage();

        if (! $this->isPublishedImage()) {
            $this->warn('Custom image selected. Make sure it is built and pushed to your registry before continuing.');
            $this->line('  docker build --push -t '.config('turbo.docker.image').' .');
            $this->newLine();

            if (! confirm(label: 'Image is pushed and ready?', default: true)) {
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

        $this->info('Preparing sandbox...');
        $sandbox->prepareSandbox();
    }

    /**
     * Check whether the sbx CLI is on the PATH.
     */
    protected function sbxAvailable(): bool
    {
        $process = new Process(['which', 'sbx']);
        $process->run();

        return $process->isSuccessful();
    }
}
