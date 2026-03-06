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

/**
 * Core PHP Framework Installation Command.
 *
 * Helps new users set up the framework with sensible defaults.
 * Run: php artisan core:install
 *
 * Options:
 *   --force          Overwrite existing configuration
 *   --no-interaction Run without prompts using defaults
 *   --dry-run        Show what would happen without executing
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'core:install
                            {--force : Overwrite existing configuration}
                            {--no-interaction : Run without prompts using defaults}
                            {--dry-run : Show what would happen without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Install and configure Core PHP Framework';

    /**
     * Installation steps for progress tracking.
     *
     * @var array<string, string>
     */
    protected array $installationSteps = [
        'environment' => 'Setting up environment file',
        'application' => 'Configuring application settings',
        'migrations' => 'Running database migrations',
        'app_key' => 'Generating application key',
        'storage_link' => 'Creating storage symlink',
    ];

    /**
     * Whether this is a dry run.
     */
    protected bool $isDryRun = false;

    /**
     * Track completed installation steps for rollback.
     *
     * @var array<string, mixed>
     */
    protected array $completedSteps = [];

    /**
     * Original .env content for rollback.
     */
    protected ?string $originalEnvContent = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->isDryRun = (bool) $this->option('dry-run');

        $this->info('');
        $this->info('  '.__('core::core.installer.title'));
        $this->info('  '.str_repeat('=', strlen(__('core::core.installer.title'))));

        if ($this->isDryRun) {
            $this->warn('  [DRY RUN] No changes will be made');
        }

        $this->info('');

        // Preserve original state for rollback (not needed in dry-run)
        if (! $this->isDryRun) {
            $this->preserveOriginalState();
        }

        try {
            // Show progress bar for all steps
            $this->info('  Installation Progress:');
            $this->info('');

            $steps = $this->getInstallationSteps();
            $progressBar = $this->output->createProgressBar(count($steps));
            $progressBar->setFormat('  %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->setMessage('Starting...');
            $progressBar->start();

            // Step 1: Environment file
            $progressBar->setMessage($this->installationSteps['environment']);
            if (! $this->setupEnvironment()) {
                $progressBar->finish();
                $this->newLine();

                return self::FAILURE;
            }
            $progressBar->advance();

            // Step 2: Application settings
            $progressBar->setMessage($this->installationSteps['application']);
            $progressBar->display();
            $this->newLine();
            $this->configureApplication();
            $progressBar->advance();

            // Step 3: Database
            $progressBar->setMessage($this->installationSteps['migrations']);
            $progressBar->display();
            if ($this->option('no-interaction') || $this->isDryRun || $this->confirm(__('core::core.installer.prompts.run_migrations'), true)) {
                $this->runMigrations();
            }
            $progressBar->advance();

            // Step 4: Generate app key if needed
            $progressBar->setMessage($this->installationSteps['app_key']);
            $this->generateAppKey();
            $progressBar->advance();

            // Step 5: Create storage link
            $progressBar->setMessage($this->installationSteps['storage_link']);
            $this->createStorageLink();
            $progressBar->advance();

            $progressBar->setMessage('Complete!');
            $progressBar->finish();
            $this->newLine(2);

            // Done!
            if ($this->isDryRun) {
                $this->info('  [DRY RUN] Installation preview complete. No changes were made.');
            } else {
                $this->info('  '.__('core::core.installer.complete'));
            }
            $this->info('');
            $this->info('  '.__('core::core.installer.next_steps').':');
            $this->info('    1. Run: valet link core');
            $this->info('    2. Visit: http://core.test');
            $this->info('');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('');
            $this->error('  Installation failed: '.$e->getMessage());
            $this->error('');

            if (! $this->isDryRun) {
                $this->rollback();
            }

            return self::FAILURE;
        }
    }

    /**
     * Get the list of installation steps to execute.
     *
     * @return array<string>
     */
    protected function getInstallationSteps(): array
    {
        return array_keys($this->installationSteps);
    }

    /**
     * Log an action in dry-run mode or execute it.
     */
    protected function dryRunOrExecute(string $description, callable $action): mixed
    {
        if ($this->isDryRun) {
            $this->info("    [WOULD] {$description}");

            return null;
        }

        return $action();
    }

    /**
     * Preserve original state for potential rollback.
     */
    protected function preserveOriginalState(): void
    {
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $this->originalEnvContent = File::get($envPath);
        }
    }

    /**
     * Rollback changes on installation failure.
     */
    protected function rollback(): void
    {
        $this->warn('  Rolling back changes...');

        // Restore original .env if we modified it
        if (isset($this->completedSteps['env_created']) && $this->completedSteps['env_created']) {
            $envPath = base_path('.env');
            if ($this->originalEnvContent !== null) {
                File::put($envPath, $this->originalEnvContent);
                $this->info('  [✓] Restored original .env file');
            } else {
                File::delete($envPath);
                $this->info('  [✓] Removed created .env file');
            }
        }

        // Restore original .env content if we only modified values
        if (isset($this->completedSteps['env_modified']) && $this->completedSteps['env_modified'] && $this->originalEnvContent !== null) {
            File::put(base_path('.env'), $this->originalEnvContent);
            $this->info('  [✓] Restored original .env configuration');
        }

        // Remove storage link if we created it
        if (isset($this->completedSteps['storage_link']) && $this->completedSteps['storage_link']) {
            $publicStorage = public_path('storage');
            if (File::exists($publicStorage) && is_link($publicStorage)) {
                File::delete($publicStorage);
                $this->info('  [✓] Removed storage symlink');
            }
        }

        // Remove SQLite file if we created it
        if (isset($this->completedSteps['sqlite_created']) && $this->completedSteps['sqlite_created']) {
            $sqlitePath = database_path('database.sqlite');
            if (File::exists($sqlitePath)) {
                File::delete($sqlitePath);
                $this->info('  [✓] Removed SQLite database file');
            }
        }

        $this->info('  Rollback complete.');
    }

    /**
     * Set up the .env file.
     */
    protected function setupEnvironment(): bool
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (File::exists($envPath) && ! $this->option('force')) {
            $this->info('  [✓] '.__('core::core.installer.env_exists'));

            return true;
        }

        if (! File::exists($envExamplePath)) {
            $this->error('  [✗] '.__('core::core.installer.env_missing'));

            return false;
        }

        if ($this->isDryRun) {
            $this->info('    [WOULD] Copy .env.example to .env');
        } else {
            File::copy($envExamplePath, $envPath);
            $this->completedSteps['env_created'] = true;
        }
        $this->info('  [✓] '.__('core::core.installer.env_created'));

        return true;
    }

    /**
     * Configure application settings.
     */
    protected function configureApplication(): void
    {
        if ($this->option('no-interaction')) {
            $this->info('  [✓] '.__('core::core.installer.default_config'));

            return;
        }

        if ($this->isDryRun) {
            $this->info('    [WOULD] Prompt for app name, domain, and database settings');
            $this->info('    [WOULD] Update .env with configured values');
            $this->info('  [✓] '.__('core::core.installer.default_config').' (dry-run)');

            return;
        }

        // App name
        $appName = $this->ask(__('core::core.installer.prompts.app_name'), __('core::core.brand.name'));
        $this->updateEnv('APP_BRAND_NAME', $appName);

        // Domain
        $domain = $this->ask(__('core::core.installer.prompts.domain'), 'core.test');
        $this->updateEnv('APP_DOMAIN', $domain);
        $this->updateEnv('APP_URL', "http://{$domain}");

        // Database
        $this->info('');
        $this->info('  Database Configuration:');
        $dbConnection = $this->choice(__('core::core.installer.prompts.db_driver'), ['sqlite', 'mysql', 'pgsql'], 0);

        if ($dbConnection === 'sqlite') {
            $this->updateEnv('DB_CONNECTION', 'sqlite');
            $this->updateEnv('DB_DATABASE', 'database/database.sqlite');

            // Create SQLite file
            $sqlitePath = database_path('database.sqlite');
            if (! File::exists($sqlitePath)) {
                File::put($sqlitePath, '');
                $this->completedSteps['sqlite_created'] = true;
                $this->info('  [✓] Created SQLite database');
            }
        } else {
            $this->updateEnv('DB_CONNECTION', $dbConnection);
            $dbHost = $this->ask(__('core::core.installer.prompts.db_host'), '127.0.0.1');
            $dbPort = $this->ask(__('core::core.installer.prompts.db_port'), $dbConnection === 'mysql' ? '3306' : '5432');
            $dbName = $this->ask(__('core::core.installer.prompts.db_name'), 'core');
            $dbUser = $this->ask(__('core::core.installer.prompts.db_user'), 'root');
            $dbPass = $this->secret(__('core::core.installer.prompts.db_password'));

            $this->updateEnv('DB_HOST', $dbHost);
            $this->updateEnv('DB_PORT', $dbPort);
            $this->updateEnv('DB_DATABASE', $dbName);
            $this->updateEnv('DB_USERNAME', $dbUser);
            $this->updateEnv('DB_PASSWORD', $dbPass ?? '');

            // Display masked confirmation (never show actual credentials)
            $this->info('');
            $this->info('  Database settings configured:');
            $this->info("    Driver:   {$dbConnection}");
            $this->info("    Host:     {$dbHost}");
            $this->info("    Port:     {$dbPort}");
            $this->info("    Database: {$dbName}");
            $this->info('    Username: '.$this->maskValue($dbUser));
            $this->info('    Password: '.$this->maskValue($dbPass ?? '', true));
        }

        $this->completedSteps['env_modified'] = true;
        $this->info('  [✓] '.__('core::core.installer.config_saved'));
    }

    /**
     * Mask a sensitive value for display.
     */
    protected function maskValue(string $value, bool $isPassword = false): string
    {
        if ($value === '') {
            return $isPassword ? '[not set]' : '[empty]';
        }

        if ($isPassword) {
            return str_repeat('*', min(strlen($value), 8));
        }

        $length = strlen($value);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        // Show first and last character with asterisks in between
        return $value[0].str_repeat('*', $length - 2).$value[$length - 1];
    }

    /**
     * Run database migrations.
     */
    protected function runMigrations(): void
    {
        $this->info('');

        if ($this->isDryRun) {
            $this->info('    [WOULD] Run: php artisan migrate --force');
            $this->info('  [✓] '.__('core::core.installer.migrations_complete').' (dry-run)');

            return;
        }

        $this->info('  Running migrations...');

        $this->call('migrate', ['--force' => true]);

        $this->info('  [✓] '.__('core::core.installer.migrations_complete'));
    }

    /**
     * Generate application key if not set.
     */
    protected function generateAppKey(): void
    {
        $key = config('app.key');

        if (empty($key) || $key === 'base64:') {
            if ($this->isDryRun) {
                $this->info('    [WOULD] Run: php artisan key:generate');
                $this->info('  [✓] '.__('core::core.installer.key_generated').' (dry-run)');
            } else {
                $this->call('key:generate');
                $this->info('  [✓] '.__('core::core.installer.key_generated'));
            }
        } else {
            $this->info('  [✓] '.__('core::core.installer.key_exists'));
        }
    }

    /**
     * Create storage symlink.
     */
    protected function createStorageLink(): void
    {
        $publicStorage = public_path('storage');

        if (File::exists($publicStorage)) {
            $this->info('  [✓] '.__('core::core.installer.storage_link_exists'));

            return;
        }

        if ($this->isDryRun) {
            $this->info('    [WOULD] Run: php artisan storage:link');
            $this->info('  [✓] '.__('core::core.installer.storage_link_created').' (dry-run)');

            return;
        }

        $this->call('storage:link');
        $this->completedSteps['storage_link'] = true;
        $this->info('  [✓] '.__('core::core.installer.storage_link_created'));
    }

    /**
     * Update a value in the .env file.
     */
    protected function updateEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        // Quote value if it contains spaces
        if (str_contains($value, ' ')) {
            $value = "\"{$value}\"";
        }

        // Check if key exists (escape regex special chars in key)
        $escapedKey = preg_quote($key, '/');
        if (preg_match("/^{$escapedKey}=/m", $content)) {
            // Update existing key
            $content = preg_replace(
                "/^{$escapedKey}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            // Add new key
            $content .= "\n{$key}={$value}";
        }

        File::put($envPath, $content);
    }

    /**
     * Get shell completion suggestions for options.
     *
     * This command has no option values that need completion hints,
     * but implements the method for consistency with other commands.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        // No argument/option values need completion for this command
        // All options are flags (--force, --no-interaction, --dry-run)
    }
}
