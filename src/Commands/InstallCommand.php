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
     * - `skills` lists turbo-bundled skills (strings) or third-party skills (arrays with name/source).
     * - `defaultEnabled` controls whether the group is pre-checked on a fresh install.
     *
     * @var array<string, array{label: string, description: string, skills: array<string|array{name: string, source: string}>, defaultEnabled: bool}>
     */
    protected array $skillGroups = [
        'laravel' => [
            'label' => 'Laravel patterns',
            'description' => 'Opinionated Laravel development patterns',
            'skills' => ['laravel-controllers', 'laravel-actions', 'laravel-validation', 'laravel-testing', 'laravel-inertia'],
            'defaultEnabled' => true,
        ],
        'project' => [
            'label' => 'Project utilities',
            'description' => 'Feedback loops enforcement + sandbox helpers',
            'skills' => ['feedback-loops', 'agent-captures'],
            'defaultEnabled' => true,
        ],
        'github' => [
            'label' => 'GitHub workflow',
            'description' => 'Issue/label/milestone patterns',
            'skills' => ['github-issue', 'github-labels', 'github-milestone'],
            'defaultEnabled' => false,
        ],
        'thirdParty' => [
            'label' => 'Third-party integrations',
            'description' => 'Recommended external skills',
            'skills' => [['name' => 'agent-browser', 'source' => 'vercel-labs/agent-browser']],
            'defaultEnabled' => false,
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

        // Step 1: Detect and configure feedback loops
        $this->configureFeedbackLoops();

        if (! $this->checkNpxAvailable()) {
            $this->error('npx is required to install skills. Please install Node.js and npm first.');
            $this->line('See: https://nodejs.org/');

            return self::FAILURE;
        }

        // Step 1: Skill selection
        $selectedSkills = $this->selectSkills();

        if (empty($selectedSkills['turbo']) && empty($selectedSkills['thirdParty'])) {
            $this->info('No skills selected.');

            return self::SUCCESS;
        }

        // Step 2: Agent selection
        $selectedAgents = $this->selectAgents();

        if (empty($selectedAgents)) {
            $this->info('No agents selected.');

            return self::SUCCESS;
        }

        // Step 3: Install skills
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
                [$skill['name']],
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
     * Prompt user to select skills via grouped two-stage UI.
     *
     * Stage 1: pick which groups to include.
     * Stage 2 (optional): customize skill selection within each group.
     *
     * @return array{turbo: array<string>, thirdParty: array<array{name: string, source: string}>}
     */
    protected function selectSkills(): array
    {
        $installed = $this->skills->getInstalledSkillNames();

        if (! $this->input->isInteractive()) {
            return $this->defaultSelection($installed);
        }

        // Stage 1: group selection
        $groupOptions = [];
        foreach ($this->skillGroups as $key => $group) {
            $groupOptions[$key] = "{$group['label']} — {$group['description']}";
        }

        $groupDefaults = $this->defaultGroups($installed);

        $selectedGroups = multiselect(
            label: 'Which skill groups do you want?',
            options: $groupOptions,
            default: $groupDefaults,
        );

        if (empty($selectedGroups)) {
            return ['turbo' => [], 'thirdParty' => []];
        }

        // Stage 2: optional per-skill customization
        $customize = confirm(
            label: 'Customize individual skills within each group?',
            default: false,
        );

        $turboSelected = [];
        $thirdPartySelected = [];

        foreach ($selectedGroups as $groupKey) {
            $group = $this->skillGroups[$groupKey];

            if ($customize) {
                [$turbo, $thirdParty] = $this->customizeGroup($groupKey, $group, $installed);
            } else {
                [$turbo, $thirdParty] = $this->allSkillsInGroup($group);
            }

            $turboSelected = array_merge($turboSelected, $turbo);
            $thirdPartySelected = array_merge($thirdPartySelected, $thirdParty);
        }

        return ['turbo' => $turboSelected, 'thirdParty' => $thirdPartySelected];
    }

    /**
     * Compute default group selection: groups with installed skills + defaultEnabled groups.
     *
     * @param  array<string>  $installed
     * @return array<string>
     */
    protected function defaultGroups(array $installed): array
    {
        $defaults = [];

        foreach ($this->skillGroups as $key => $group) {
            $hasInstalled = collect($group['skills'])->contains(
                fn ($skill) => in_array($this->skillName($skill), $installed, true)
            );

            if ($hasInstalled || $group['defaultEnabled']) {
                $defaults[] = $key;
            }
        }

        return $defaults;
    }

    /**
     * Prompt for skills within a group. Pre-checks installed skills + all new ones.
     *
     * @param  array{label: string, description: string, skills: array<string|array{name: string, source: string}>, defaultEnabled: bool}  $group
     * @param  array<string>  $installed
     * @return array{0: array<string>, 1: array<array{name: string, source: string}>}
     */
    protected function customizeGroup(string $groupKey, array $group, array $installed): array
    {
        $options = [];
        $defaults = [];

        foreach ($group['skills'] as $skill) {
            $name = $this->skillName($skill);
            $isInstalled = in_array($name, $installed, true);
            $options[$name] = $isInstalled ? "{$name} (installed)" : $name;
            $defaults[] = $name;
        }

        $selected = multiselect(
            label: "{$group['label']} — pick skills to install:",
            options: $options,
            default: $defaults,
        );

        return $this->partitionSelection($group, $selected);
    }

    /**
     * Return all skills in a group (when user skips customization).
     *
     * @param  array{label: string, description: string, skills: array<string|array{name: string, source: string}>, defaultEnabled: bool}  $group
     * @return array{0: array<string>, 1: array<array{name: string, source: string}>}
     */
    protected function allSkillsInGroup(array $group): array
    {
        $selected = array_map(fn ($skill) => $this->skillName($skill), $group['skills']);

        return $this->partitionSelection($group, $selected);
    }

    /**
     * Split selected skill names into turbo + thirdParty based on their definitions.
     *
     * @param  array{label: string, description: string, skills: array<string|array{name: string, source: string}>, defaultEnabled: bool}  $group
     * @param  array<string>  $selected
     * @return array{0: array<string>, 1: array<array{name: string, source: string}>}
     */
    protected function partitionSelection(array $group, array $selected): array
    {
        $turbo = [];
        $thirdParty = [];

        foreach ($group['skills'] as $skill) {
            $name = $this->skillName($skill);
            if (! in_array($name, $selected, true)) {
                continue;
            }

            if (is_array($skill)) {
                $thirdParty[] = $skill;
            } else {
                $turbo[] = $skill;
            }
        }

        return [$turbo, $thirdParty];
    }

    /**
     * Extract the skill name from a string or definition array.
     *
     * @param  string|array{name: string, source: string}  $skill
     */
    protected function skillName(string|array $skill): string
    {
        return is_array($skill) ? $skill['name'] : $skill;
    }

    /**
     * Non-interactive default: install all skills in default-enabled groups.
     *
     * @param  array<string>  $installed
     * @return array{turbo: array<string>, thirdParty: array<array{name: string, source: string}>}
     */
    protected function defaultSelection(array $installed): array
    {
        $turboSelected = [];
        $thirdPartySelected = [];

        $groupKeys = $this->defaultGroups($installed);

        foreach ($groupKeys as $groupKey) {
            [$turbo, $thirdParty] = $this->allSkillsInGroup($this->skillGroups[$groupKey]);
            $turboSelected = array_merge($turboSelected, $turbo);
            $thirdPartySelected = array_merge($thirdPartySelected, $thirdParty);
        }

        return ['turbo' => $turboSelected, 'thirdParty' => $thirdPartySelected];
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

        $image = text(
            label: 'Docker image name',
            hint: 'Press enter to use the published image. To extend it, enter your own registry image (e.g. docker.io/my-org/my-sandbox:latest).',
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

        $wantsDocker = confirm(
            label: 'Create Docker sandbox for this project?',
            default: true,
        );

        if (! $wantsDocker) {
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

        $this->installSandboxPlugins($sandbox);
    }

    /**
     * Check whether the sbx CLI is on the PATH.
     */
    protected function sbxAvailable(): bool
    {
        return trim((string) shell_exec('command -v sbx')) !== '';
    }

    /**
     * Install superpowers plugins into the sandbox.
     */
    protected function installSandboxPlugins(DockerSandbox $sandbox): void
    {
        $this->info('Installing sandbox plugins...');

        $outputCallback = fn ($type, $buffer) => $this->output->write($buffer);

        $result = $sandbox->runCommand(
            ['plugin', 'marketplace', 'add', 'obra/superpowers-marketplace'],
            $outputCallback,
        );

        $resultOutput = $result->getErrorOutput().$result->getOutput();

        if (! $result->isSuccessful() && ! str_contains($resultOutput, 'already installed')) {
            $this->warn('Failed to install marketplace plugin. You may need to authenticate first.');
            $this->line('Run <comment>turbo:claude</comment> and use <comment>/login</comment> to authenticate.');

            return;
        }

        $sandbox->runCommand(
            ['plugin', 'install', 'superpowers@superpowers-marketplace'],
            $outputCallback,
        );
    }
}
