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
use Illuminate\Support\Str;

/**
 * Generate a new Website scaffold.
 *
 * Creates a domain-isolated website in the Website namespace
 * that is loaded based on incoming HTTP host.
 *
 * Usage: php artisan make:website MarketingSite --domain=marketing.test
 */
class MakeWebsiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:website
                            {name : The name of the website (e.g., MarketingSite, Blog)}
                            {--domain= : Primary domain pattern (e.g., example.test, example.com)}
                            {--web : Include web routes}
                            {--admin : Include admin routes}
                            {--api : Include API routes}
                            {--all : Include all route types}
                            {--force : Overwrite existing website}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new domain-isolated website';

    /**
     * Files created during generation for summary table.
     *
     * @var array<array{file: string, description: string}>
     */
    protected array $createdFiles = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $domain = $this->option('domain') ?: Str::snake($name, '-').'.test';
        $websitePath = $this->getWebsitePath($name);

        if (File::isDirectory($websitePath) && ! $this->option('force')) {
            $this->newLine();
            $this->components->error("Website [{$name}] already exists!");
            $this->newLine();
            $this->components->warn('Use --force to overwrite the existing website.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Creating website: <comment>{$name}</comment>");
        $this->components->twoColumnDetail('Domain', "<fg=yellow>{$domain}</>");
        $this->newLine();

        // Create directory structure
        $this->createDirectoryStructure($websitePath);

        // Create Boot.php
        $this->createBootFile($websitePath, $name, $domain);

        // Create optional route files
        $this->createOptionalFiles($websitePath, $name);

        // Show summary table of created files
        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Created Files</>', '<fg=gray>Description</>');
        foreach ($this->createdFiles as $file) {
            $this->components->twoColumnDetail(
                "<fg=cyan>{$file['file']}</>",
                "<fg=gray>{$file['description']}</>"
            );
        }

        $this->newLine();
        $this->components->info("Website [{$name}] created successfully!");
        $this->newLine();
        $this->components->twoColumnDetail('Location', "<fg=yellow>{$websitePath}</>");
        $this->newLine();

        $this->components->info('Next steps:');
        $this->line("  <fg=gray>1.</> Configure your local dev server to serve <fg=yellow>{$domain}</>");
        $this->line('     <fg=gray>(e.g.,</> valet link '.Str::snake($name, '-').'<fg=gray>)</>');
        $this->line("  <fg=gray>2.</> Visit <fg=cyan>http://{$domain}</> to see your website");
        $this->line('  <fg=gray>3.</> Add routes, views, and controllers as needed');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the path for the website.
     */
    protected function getWebsitePath(string $name): string
    {
        // Websites go in app/Website for consuming applications
        return base_path("app/Website/{$name}");
    }

    /**
     * Create the directory structure for the website.
     */
    protected function createDirectoryStructure(string $websitePath): void
    {
        $directories = [
            $websitePath,
            "{$websitePath}/View",
            "{$websitePath}/View/Blade",
            "{$websitePath}/View/Blade/layouts",
        ];

        if ($this->hasRoutes()) {
            $directories[] = "{$websitePath}/Routes";
        }

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $this->components->task('Creating directory structure', fn () => true);
    }

    /**
     * Check if any route handlers are requested.
     */
    protected function hasRoutes(): bool
    {
        return $this->option('web')
            || $this->option('admin')
            || $this->option('api')
            || $this->option('all')
            || ! $this->hasAnyOption();
    }

    /**
     * Check if any specific option was provided.
     */
    protected function hasAnyOption(): bool
    {
        return $this->option('web')
            || $this->option('admin')
            || $this->option('api')
            || $this->option('all');
    }

    /**
     * Create the Boot.php file.
     */
    protected function createBootFile(string $websitePath, string $name, string $domain): void
    {
        $namespace = "Website\\{$name}";
        $domainPattern = $this->buildDomainPattern($domain);
        $listeners = $this->buildListenersArray();
        $handlers = $this->buildHandlerMethods($name);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\Events\DomainResolving;
{$this->buildUseStatements()}
use Illuminate\Support\ServiceProvider;

/**
 * {$name} Website - Domain-isolated website provider.
 *
 * This website is loaded when the incoming HTTP host matches
 * the domain pattern defined in \$domains.
 */
class Boot extends ServiceProvider
{
    /**
     * Domain patterns this website responds to.
     *
     * Uses regex patterns. Common examples:
     *   - '/^example\\.test\$/'           - exact match
     *   - '/^example\\.(com|test)\$/'     - multiple TLDs
     *   - '/^(www\\.)?example\\.com\$/'   - optional www
     *
     * @var array<string>
     */
    public static array \$domains = [
        '{$domainPattern}',
    ];

    /**
     * Events this module listens to for lazy loading.
     *
     * @var array<class-string, string>
     */
    public static array \$listens = [
        DomainResolving::class => 'onDomainResolving',
{$listeners}
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Handle domain resolution - register if domain matches.
     */
    public function onDomainResolving(DomainResolving \$event): void
    {
        foreach (self::\$domains as \$pattern) {
            if (\$event->matches(\$pattern)) {
                \$event->register(self::class);

                return;
            }
        }
    }
{$handlers}
}

PHP;

        File::put("{$websitePath}/Boot.php", $content);
        $this->createdFiles[] = ['file' => 'Boot.php', 'description' => 'Domain-isolated website provider'];
        $this->components->task('Creating Boot.php', fn () => true);
    }

    /**
     * Build the domain regex pattern.
     */
    protected function buildDomainPattern(string $domain): string
    {
        // Escape dots and create a regex pattern
        $escaped = preg_quote($domain, '/');

        return '/^'.$escaped.'$/';
    }

    /**
     * Build the use statements for the Boot file.
     */
    protected function buildUseStatements(): string
    {
        $statements = [];

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $statements[] = 'use Core\Events\WebRoutesRegistering;';
        }

        if ($this->option('admin') || $this->option('all')) {
            $statements[] = 'use Core\Events\AdminPanelBooting;';
        }

        if ($this->option('api') || $this->option('all')) {
            $statements[] = 'use Core\Events\ApiRoutesRegistering;';
        }

        return implode("\n", $statements);
    }

    /**
     * Build the listeners array content (excluding DomainResolving).
     */
    protected function buildListenersArray(): string
    {
        $listeners = [];

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $listeners[] = "        WebRoutesRegistering::class => 'onWebRoutes',";
        }

        if ($this->option('admin') || $this->option('all')) {
            $listeners[] = "        AdminPanelBooting::class => 'onAdminPanel',";
        }

        if ($this->option('api') || $this->option('all')) {
            $listeners[] = "        ApiRoutesRegistering::class => 'onApiRoutes',";
        }

        return implode("\n", $listeners);
    }

    /**
     * Build the handler methods.
     */
    protected function buildHandlerMethods(string $name): string
    {
        $methods = [];
        $websiteName = Str::snake($name);

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $methods[] = <<<PHP

    /**
     * Register web routes and views.
     */
    public function onWebRoutes(WebRoutesRegistering \$event): void
    {
        \$event->views('{$websiteName}', __DIR__.'/View/Blade');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            \$event->routes(fn () => require __DIR__.'/Routes/web.php');
        }
    }
PHP;
        }

        if ($this->option('admin') || $this->option('all')) {
            $methods[] = <<<'PHP'

    /**
     * Register admin panel routes.
     */
    public function onAdminPanel(AdminPanelBooting $event): void
    {
        if (file_exists(__DIR__.'/Routes/admin.php')) {
            $event->routes(fn () => require __DIR__.'/Routes/admin.php');
        }
    }
PHP;
        }

        if ($this->option('api') || $this->option('all')) {
            $methods[] = <<<'PHP'

    /**
     * Register API routes.
     */
    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        if (file_exists(__DIR__.'/Routes/api.php')) {
            $event->routes(fn () => require __DIR__.'/Routes/api.php');
        }
    }
PHP;
        }

        return implode("\n", $methods);
    }

    /**
     * Create optional files based on flags.
     */
    protected function createOptionalFiles(string $websitePath, string $name): void
    {
        $websiteName = Str::snake($name);

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $this->createWebRoutes($websitePath, $websiteName);
            $this->createLayout($websitePath, $name);
            $this->createHomepage($websitePath, $websiteName);
        }

        if ($this->option('admin') || $this->option('all')) {
            $this->createAdminRoutes($websitePath, $websiteName);
        }

        if ($this->option('api') || $this->option('all')) {
            $this->createApiRoutes($websitePath, $websiteName);
        }
    }

    /**
     * Create web routes file.
     */
    protected function createWebRoutes(string $websitePath, string $websiteName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$websiteName} Web Routes
|--------------------------------------------------------------------------
|
| Public web routes for this website.
|
*/

Route::get('/', function () {
    return view('{$websiteName}::home');
})->name('{$websiteName}.home');

PHP;

        File::put("{$websitePath}/Routes/web.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/web.php', 'description' => 'Public web routes'];
        $this->components->task('Creating Routes/web.php', fn () => true);
    }

    /**
     * Create admin routes file.
     */
    protected function createAdminRoutes(string $websitePath, string $websiteName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$websiteName} Admin Routes
|--------------------------------------------------------------------------
|
| Admin routes for this website.
|
*/

Route::prefix('admin/{$websiteName}')->name('{$websiteName}.admin.')->group(function () {
    Route::get('/', function () {
        return 'Admin dashboard for {$websiteName}';
    })->name('index');
});

PHP;

        File::put("{$websitePath}/Routes/admin.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/admin.php', 'description' => 'Admin panel routes'];
        $this->components->task('Creating Routes/admin.php', fn () => true);
    }

    /**
     * Create API routes file.
     */
    protected function createApiRoutes(string $websitePath, string $websiteName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$websiteName} API Routes
|--------------------------------------------------------------------------
|
| API routes for this website.
|
*/

Route::prefix('{$websiteName}')->name('api.{$websiteName}.')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok', 'website' => '{$websiteName}']);
    })->name('health');
});

PHP;

        File::put("{$websitePath}/Routes/api.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/api.php', 'description' => 'REST API routes'];
        $this->components->task('Creating Routes/api.php', fn () => true);
    }

    /**
     * Create a base layout file.
     */
    protected function createLayout(string $websitePath, string $name): void
    {
        $content = <<<BLADE
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ \$title ?? '{$name}' }}</title>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="/" class="text-xl font-bold text-gray-800">
                            {$name}
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            {{ \$slot }}
        </main>
    </div>
</body>
</html>

BLADE;

        File::put("{$websitePath}/View/Blade/layouts/app.blade.php", $content);
        $this->createdFiles[] = ['file' => 'View/Blade/layouts/app.blade.php', 'description' => 'Base layout template'];
        $this->components->task('Creating View/Blade/layouts/app.blade.php', fn () => true);
    }

    /**
     * Create a homepage view.
     */
    protected function createHomepage(string $websitePath, string $websiteName): void
    {
        $name = Str::studly($websiteName);

        $content = <<<BLADE
<x-{$websiteName}::layouts.app>
    <x-slot name="title">Welcome - {$name}</x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-3xl font-bold mb-4">Welcome to {$name}</h1>
                    <p class="text-gray-600">
                        This is your new website. Start building something amazing!
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-{$websiteName}::layouts.app>

BLADE;

        File::put("{$websitePath}/View/Blade/home.blade.php", $content);
        $this->createdFiles[] = ['file' => 'View/Blade/home.blade.php', 'description' => 'Homepage view'];
        $this->components->task('Creating View/Blade/home.blade.php', fn () => true);
    }

    /**
     * Get shell completion suggestions for arguments and options.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            // Suggest common website naming patterns
            $suggestions->suggestValues([
                'MarketingSite',
                'Blog',
                'Documentation',
                'LandingPage',
                'Portal',
                'Dashboard',
                'Support',
            ]);
        }

        if ($input->mustSuggestOptionValuesFor('domain')) {
            // Suggest common development domains
            $suggestions->suggestValues([
                'example.test',
                'app.test',
                'site.test',
                'dev.test',
            ]);
        }
    }
}
