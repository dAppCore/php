<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Create a new Core PHP Framework project.
 *
 * Similar to `laravel new` but creates a project pre-configured
 * with Core PHP Framework packages (core, admin, api, mcp).
 *
 * Usage: php artisan core:new my-project
 *        php artisan core:new my-project --template=github.com/host-uk/core-template
 */
class NewProjectCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'core:new
                            {name : The name of the project directory}
                            {--template= : GitHub template repository (default: host-uk/core-template)}
                            {--branch=main : Branch to clone from template}
                            {--no-install : Skip composer install and setup}
                            {--dev : Install packages in dev mode (with path repos)}
                            {--force : Overwrite existing directory}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Core PHP Framework project';

    /**
     * Default template repository.
     */
    protected string $defaultTemplate = 'host-uk/core-template';

    /**
     * Created files and directories for summary.
     */
    protected array $createdPaths = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $directory = getcwd().'/'.$name;

        // Validate project name
        if (! $this->validateProjectName($name)) {
            return self::FAILURE;
        }

        // Check if directory exists
        if (File::isDirectory($directory) && ! $this->option('force')) {
            $this->newLine();
            $this->components->error("Directory [{$name}] already exists!");
            $this->newLine();
            $this->components->warn('Use --force to overwrite the existing directory.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('  ╔═══════════════════════════════════════════╗');
        $this->components->info('  ║   Core PHP Framework Project Creator     ║');
        $this->components->info('  ╚═══════════════════════════════════════════╝');
        $this->newLine();

        $template = $this->option('template') ?: $this->defaultTemplate;
        $this->components->twoColumnDetail('<fg=cyan>Project Name</>', $name);
        $this->components->twoColumnDetail('<fg=cyan>Template</>', $template);
        $this->components->twoColumnDetail('<fg=cyan>Directory</>', $directory);
        $this->newLine();

        try {
            // Step 1: Create project from template
            $this->components->task('Creating project from template', function () use ($directory, $template, $name) {
                return $this->createFromTemplate($directory, $template, $name);
            });

            // Step 2: Install dependencies
            if (! $this->option('no-install')) {
                $this->components->task('Installing Composer dependencies', function () use ($directory) {
                    return $this->installDependencies($directory);
                });

                // Step 3: Run core:install
                $this->components->task('Running framework installation', function () use ($directory) {
                    return $this->runCoreInstall($directory);
                });
            }

            // Success!
            $this->newLine();
            $this->components->info('  ✓ Project created successfully!');
            $this->newLine();

            $this->components->info('  Next steps:');
            $this->line("    <fg=gray>1.</> cd {$name}");
            if ($this->option('no-install')) {
                $this->line('    <fg=gray>2.</> composer install');
                $this->line('    <fg=gray>3.</> php artisan core:install');
                $this->line('    <fg=gray>4.</> php artisan serve');
            } else {
                $this->line('    <fg=gray>2.</> php artisan serve');
            }
            $this->newLine();

            $this->showPackageInfo();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error('  Project creation failed: '.$e->getMessage());
            $this->newLine();

            // Cleanup on failure
            if (File::isDirectory($directory)) {
                $cleanup = $this->confirm('Remove failed project directory?', true);
                if ($cleanup) {
                    File::deleteDirectory($directory);
                    $this->components->info('  Cleaned up project directory.');
                }
            }

            return self::FAILURE;
        }
    }

    /**
     * Validate project name.
     */
    protected function validateProjectName(string $name): bool
    {
        if (empty($name)) {
            $this->components->error('Project name cannot be empty');

            return false;
        }

        if (! preg_match('/^[a-z0-9_-]+$/i', $name)) {
            $this->components->error('Project name can only contain letters, numbers, hyphens, and underscores');

            return false;
        }

        if (in_array(strtolower($name), ['vendor', 'app', 'test', 'tests', 'src', 'public'])) {
            $this->components->error("Project name '{$name}' is reserved");

            return false;
        }

        return true;
    }

    /**
     * Create project from template repository.
     */
    protected function createFromTemplate(string $directory, string $template, string $projectName): bool
    {
        $branch = $this->option('branch');

        // If force, delete existing directory
        if ($this->option('force') && File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        // Check if template is a URL or repo slug
        $templateUrl = $this->resolveTemplateUrl($template);

        // Clone the template
        $result = Process::run("git clone --branch {$branch} --single-branch --depth 1 {$templateUrl} {$directory}");

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to clone template: {$result->errorOutput()}");
        }

        // Remove .git directory to make it a fresh repo
        File::deleteDirectory("{$directory}/.git");

        // Update composer.json with project name
        $this->updateComposerJson($directory, $projectName);

        // Initialize new git repository
        Process::run("cd {$directory} && git init");
        Process::run("cd {$directory} && git add .");
        Process::run("cd {$directory} && git commit -m \"Initial commit from Core PHP Framework template\"");

        return true;
    }

    /**
     * Resolve template to full git URL.
     */
    protected function resolveTemplateUrl(string $template): string
    {
        // If already a URL, return as-is
        if (str_starts_with($template, 'http://') || str_starts_with($template, 'https://')) {
            return $template;
        }

        // If contains .git, treat as SSH URL
        if (str_contains($template, '.git')) {
            return $template;
        }

        // Otherwise, assume GitHub slug
        return "https://github.com/{$template}.git";
    }

    /**
     * Update composer.json with project name.
     */
    protected function updateComposerJson(string $directory, string $projectName): void
    {
        $composerPath = "{$directory}/composer.json";
        if (! File::exists($composerPath)) {
            return;
        }

        $composer = json_decode(File::get($composerPath), true);
        $composer['name'] = $this->generateComposerName($projectName);
        $composer['description'] = "Core PHP Framework application - {$projectName}";

        // Update namespace if using default App namespace
        if (isset($composer['autoload']['psr-4']['App\\'])) {
            $studlyName = Str::studly($projectName);
            $composer['autoload']['psr-4']["{$studlyName}\\"] = 'app/';
            unset($composer['autoload']['psr-4']['App\\']);
        }

        File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Generate composer package name from project name.
     */
    protected function generateComposerName(string $projectName): string
    {
        $vendor = $this->ask('Composer vendor name', 'my-company');
        $package = Str::slug($projectName);

        return "{$vendor}/{$package}";
    }

    /**
     * Install composer dependencies.
     */
    protected function installDependencies(string $directory): bool
    {
        $composerBin = $this->findComposer();

        $command = $this->option('dev')
            ? "{$composerBin} install --prefer-source"
            : "{$composerBin} install";

        $result = Process::run("cd {$directory} && {$command}");

        if (! $result->successful()) {
            throw new \RuntimeException("Composer install failed: {$result->errorOutput()}");
        }

        return true;
    }

    /**
     * Run core:install command.
     */
    protected function runCoreInstall(string $directory): bool
    {
        $result = Process::run("cd {$directory} && php artisan core:install --no-interaction");

        if (! $result->successful()) {
            throw new \RuntimeException("core:install failed: {$result->errorOutput()}");
        }

        return true;
    }

    /**
     * Find the composer binary.
     */
    protected function findComposer(): string
    {
        // Check if composer is in PATH
        $result = Process::run('which composer');
        if ($result->successful()) {
            return trim($result->output());
        }

        // Check common locations
        $locations = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            $_SERVER['HOME'].'/.composer/composer.phar',
        ];

        foreach ($locations as $location) {
            if (File::exists($location)) {
                return $location;
            }
        }

        return 'composer'; // Fallback, will fail if not in PATH
    }

    /**
     * Show package information.
     */
    protected function showPackageInfo(): void
    {
        $this->components->info('  📦 Installed Core PHP Packages:');
        $this->components->twoColumnDetail('    <fg=cyan>host-uk/core</>', 'Core framework components');
        $this->components->twoColumnDetail('    <fg=cyan>host-uk/core-admin</>', 'Admin panel & Livewire modals');
        $this->components->twoColumnDetail('    <fg=cyan>host-uk/core-api</>', 'REST API with scopes & webhooks');
        $this->components->twoColumnDetail('    <fg=cyan>host-uk/core-mcp</>', 'Model Context Protocol tools');
        $this->newLine();

        $this->components->info('  📚 Documentation:');
        $this->components->twoColumnDetail('    <fg=yellow>https://github.com/host-uk/core-php</>', 'GitHub Repository');
        $this->components->twoColumnDetail('    <fg=yellow>https://docs.core-php.dev</>', 'Official Docs (future)');
        $this->newLine();
    }

    /**
     * Get shell completion suggestions.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            // Suggest common project naming patterns
            $suggestions->suggestValues([
                'my-app',
                'api-service',
                'admin-panel',
                'saas-platform',
            ]);
        }

        if ($input->mustSuggestOptionValuesFor('template')) {
            // Suggest known templates
            $suggestions->suggestValues([
                'host-uk/core-template',
                'host-uk/core-api-template',
                'host-uk/core-admin-template',
            ]);
        }
    }
}
