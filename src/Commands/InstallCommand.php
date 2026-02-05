<?php

namespace Springloaded\Turbo\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Springloaded\Turbo\Commands\Concerns\ProcessesSkills;
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
                $this->skills->getPackagePath(),
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
            $this->info("Installing {$skill['name']}...");

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

        // Step 4: GitHub token
        $this->configureGitHubToken();

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

        return $process->getExitCode();
    }

    /**
     * Prompt for GitHub token and create settings.local.json.
     */
    protected function configureGitHubToken(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $settingsPath = base_path('.claude/settings.local.json');

        if ($this->files->exists($settingsPath)) {
            return;
        }

        $wantsToken = confirm(
            label: 'Do you have a GitHub token to configure? (enables gh CLI access for Claude)',
            default: false,
        );

        if (! $wantsToken) {
            return;
        }

        $token = text(
            label: 'Enter your GitHub token',
            required: true,
        );

        if (empty($token)) {
            return;
        }

        $claudeDir = base_path('.claude');
        if (! $this->files->isDirectory($claudeDir)) {
            $this->files->makeDirectory($claudeDir, 0755, true);
        }

        $settings = [
            'env' => [
                'GITHUB_TOKEN' => $token,
            ],
        ];

        $this->files->put(
            $settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info('Created '.$settingsPath.' with GitHub token');

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
     * Ask if the user wants to set up Docker sandbox.
     */
    protected function offerDockerSetup(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $wantsDocker = confirm(
            label: 'Set up Docker sandbox? (builds the Turbo sandbox image)',
            default: false,
        );

        if ($wantsDocker) {
            $this->call('turbo:build');
        }
    }
}
