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
     * Recommended third-party skills to offer during install.
     *
     * @var array<array{name: string, source: string}>
     */
    protected array $recommendedSkills = [
        ['name' => 'agent-browser', 'source' => 'vercel-labs/agent-browser'],
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

        // Step 6: Browser feedback extension
        $this->offerFeedbackSetup();

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
     * Prompt user to select which skills to install.
     *
     * @return array{turbo: array<string>, thirdParty: array<array{name: string, source: string}>}
     */
    protected function selectSkills(): array
    {
        $turboSkills = $this->skills->getAvailableSkills();

        // Build choices: turbo skills + recommended third-party
        $choices = [];
        foreach ($turboSkills as $skill) {
            $choices["turbo:{$skill}"] = "{$skill}";
        }
        foreach ($this->recommendedSkills as $skill) {
            $choices["recommended:{$skill['name']}"] = "{$skill['name']} (recommended)";
        }

        if ($this->input->isInteractive()) {
            $selected = multiselect(
                label: 'Which skills would you like to install?',
                options: $choices,
                default: array_keys($choices),
            );
        } else {
            $selected = array_keys($choices);
        }

        // Split selections back into turbo vs third-party
        $turboSelected = [];
        $thirdPartySelected = [];

        foreach ($selected as $key) {
            if (str_starts_with($key, 'turbo:')) {
                $turboSelected[] = str_replace('turbo:', '', $key);
            } elseif (str_starts_with($key, 'recommended:')) {
                $name = str_replace('recommended:', '', $key);
                $skill = collect($this->recommendedSkills)->firstWhere('name', $name);
                if ($skill) {
                    $thirdPartySelected[] = $skill;
                }
            }
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
        $default = 'turbo/'.basename(base_path());

        $image = text(
            label: 'Docker image name',
            default: $default,
            required: true,
        );

        $this->writeDockerImageToConfig($image);

        config(['turbo.docker.image' => $image]);
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
     * Ask if the user wants to set up Docker sandbox.
     */
    protected function offerDockerSetup(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $wantsDocker = confirm(
            label: 'Set up Docker sandbox? (builds the Turbo sandbox image)',
            default: true,
        );

        if (! $wantsDocker) {
            return;
        }

        // Configure image name
        $this->configureDockerImage();

        // Step 1: Build the image
        $exitCode = $this->call('turbo:build');

        if ($exitCode !== self::SUCCESS) {
            return;
        }

        // Step 2: Create the sandbox
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

        // Step 3: Authenticate Claude inside the sandbox
        // $this->authenticateSandbox($sandbox);

        // Step 4: Install plugins
        $this->installSandboxPlugins($sandbox);
    }

    /**
     * Launch an interactive Claude session for the user to authenticate.
     *
     * The user runs /login inside the session, then exits.
     * After the session closes, the sandbox is authenticated.
     */
    protected function authenticateSandbox(DockerSandbox $sandbox): void
    {
        $this->newLine();
        $this->info('Launching Claude session for authentication...');
        $this->newLine();

        $prompt = 'Welcome! To complete the Turbo installation, please authenticate your Claude account. '
            .'Run /login to begin authentication, complete the browser flow, then run /exit when you\'re done.';

        $process = $sandbox->interactiveProcess(['-p', $prompt]);
        $process->run();
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

    /**
     * Offer to set up the browser feedback extension.
     */
    protected function offerFeedbackSetup(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $wantsFeedback = confirm(
            label: 'Set up browser feedback extension?',
            hint: 'Installs native messaging host and registers the MCP server for screenshot feedback.',
            default: true,
        );

        if (! $wantsFeedback) {
            return;
        }

        $this->installNativeMessagingHost();
        $this->registerMcpServer();
        $this->configureExtensionProject();
    }

    /**
     * Register the turbo-feedback MCP server in .claude/settings.json.
     */
    protected function registerMcpServer(): void
    {
        $settingsPath = base_path('.claude/settings.json');

        $dir = dirname($settingsPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $settings = file_exists($settingsPath)
            ? json_decode(file_get_contents($settingsPath), true)
            : [];

        $settings['mcpServers'] ??= [];
        $settings['mcpServers']['turbo-feedback'] = [
            'command' => 'php',
            'args' => ['artisan', 'mcp:start', 'turbo-feedback'],
        ];

        file_put_contents(
            $settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->components->info('Registered turbo-feedback MCP server.');
    }

    /**
     * Install the Chrome native messaging host for browser feedback.
     */
    protected function installNativeMessagingHost(): void
    {
        $hostScriptSource = dirname(__DIR__, 2).'/resources/native-messaging/host.js';

        // Determine Chrome native messaging host directory
        $nativeHostDir = match (PHP_OS_FAMILY) {
            'Darwin' => $_SERVER['HOME'].'/Library/Application Support/Google/Chrome/NativeMessagingHosts',
            'Linux' => $_SERVER['HOME'].'/.config/google-chrome/NativeMessagingHosts',
            default => null,
        };

        if (! $nativeHostDir) {
            $this->components->warn('Native messaging host setup is only supported on macOS and Linux.');

            return;
        }

        if (! is_dir($nativeHostDir)) {
            mkdir($nativeHostDir, 0755, true);
        }

        // Copy host script
        $hostScriptDest = $nativeHostDir.'/com.springloaded.turbo_feedback.js';
        copy($hostScriptSource, $hostScriptDest);
        chmod($hostScriptDest, 0755);

        // Write manifest with resolved path
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/resources/native-messaging/com.springloaded.turbo_feedback.json'),
            true
        );
        $manifest['path'] = $hostScriptDest;
        // Extension ID will be set after Chrome Web Store publish or during dev sideload
        // For now, use a placeholder that the developer updates
        $manifest['allowed_origins'] = ['chrome-extension://<EXTENSION_ID>/'];

        file_put_contents(
            $nativeHostDir.'/com.springloaded.turbo_feedback.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->components->info('Native messaging host installed.');
    }

    /**
     * Configure the browser extension with the project's URL and workspace path via deep link.
     */
    protected function configureExtensionProject(): void
    {
        // Read APP_URL from workspace .env
        $envPath = base_path('.env');
        $appUrl = null;

        if (file_exists($envPath)) {
            $env = file_get_contents($envPath);
            if (preg_match('/^APP_URL=(.+)$/m', $env, $matches)) {
                $appUrl = trim($matches[1], '"\'');
            }
        }

        if (! $appUrl) {
            $this->components->warn('APP_URL not found in .env — skipping extension configuration.');

            return;
        }

        $host = parse_url($appUrl, PHP_URL_HOST);
        $workspace = base_path();

        $confirmed = confirm(
            label: "Configure browser extension for {$host}?",
            default: true,
            hint: "Workspace: {$workspace}"
        );

        if (! $confirmed) {
            return;
        }

        $deepLink = 'turbo-feedback://add-project?'.http_build_query([
            'url' => $host,
            'workspace' => $workspace,
        ]);

        // Try to open in browser
        $opened = match (PHP_OS_FAMILY) {
            'Darwin' => exec('open '.escapeshellarg($deepLink).' 2>/dev/null', result_code: $exitCode) !== false && $exitCode === 0,
            'Linux' => exec('xdg-open '.escapeshellarg($deepLink).' 2>/dev/null', result_code: $exitCode) !== false && $exitCode === 0,
            default => false,
        };

        if ($opened) {
            $this->components->info('Opened browser to configure extension.');
        }

        $this->line("  Deep link: <comment>{$deepLink}</comment>");
        $this->components->info("Copy the deep link above if the browser didn't open automatically.");
    }
}
