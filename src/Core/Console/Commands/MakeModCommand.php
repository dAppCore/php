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
 * Generate a new module scaffold.
 *
 * Creates a module in the Mod namespace with the standard Boot.php
 * pattern for event-driven loading.
 *
 * Usage: php artisan make:mod Example
 */
class MakeModCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:mod
                            {name : The name of the module (e.g., Example, UserManagement)}
                            {--web : Include web routes handler}
                            {--admin : Include admin panel handler}
                            {--api : Include API routes handler}
                            {--console : Include console commands handler}
                            {--all : Include all handlers}
                            {--force : Overwrite existing module}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new module in the Mod namespace';

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
        $modulePath = $this->getModulePath($name);

        if (File::isDirectory($modulePath) && ! $this->option('force')) {
            $this->newLine();
            $this->components->error("Module [{$name}] already exists!");
            $this->newLine();
            $this->components->warn('Use --force to overwrite the existing module.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Creating module: <comment>{$name}</comment>");
        $this->newLine();

        // Create directory structure
        $this->createDirectoryStructure($modulePath);

        // Create Boot.php
        $this->createBootFile($modulePath, $name);

        // Create optional route files based on flags
        $this->createOptionalFiles($modulePath, $name);

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
        $this->components->info("Module [{$name}] created successfully!");
        $this->newLine();
        $this->components->twoColumnDetail('Location', "<fg=yellow>{$modulePath}</>");
        $this->newLine();

        $this->components->info('Next steps:');
        $this->line('  <fg=gray>1.</> Add your module logic to the Boot.php event handlers');
        $this->line('  <fg=gray>2.</> Create Models, Views, and Controllers as needed');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the path for the module.
     */
    protected function getModulePath(string $name): string
    {
        // Check for packages structure first (monorepo)
        $packagesPath = base_path("packages/core-php/src/Mod/{$name}");
        if (File::isDirectory(dirname($packagesPath))) {
            return $packagesPath;
        }

        // Fall back to app/Mod for consuming applications
        return base_path("app/Mod/{$name}");
    }

    /**
     * Create the directory structure for the module.
     */
    protected function createDirectoryStructure(string $modulePath): void
    {
        $directories = [
            $modulePath,
            "{$modulePath}/Models",
            "{$modulePath}/View",
            "{$modulePath}/View/Blade",
        ];

        if ($this->hasRoutes()) {
            $directories[] = "{$modulePath}/Routes";
        }

        if ($this->option('console') || $this->option('all')) {
            $directories[] = "{$modulePath}/Console";
            $directories[] = "{$modulePath}/Console/Commands";
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
            || $this->option('all');
    }

    /**
     * Create the Boot.php file.
     */
    protected function createBootFile(string $modulePath, string $name): void
    {
        $namespace = $this->resolveNamespace($modulePath, $name);
        $listeners = $this->buildListenersArray();
        $handlers = $this->buildHandlerMethods($name);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$this->buildUseStatements()}

/**
 * {$name} Module - Event-driven module registration.
 *
 * This module uses the lazy loading pattern where handlers
 * are only invoked when their corresponding events fire.
 */
class Boot
{
    /**
     * Events this module listens to for lazy loading.
     *
     * @var array<class-string, string>
     */
    public static array \$listens = [
{$listeners}
    ];
{$handlers}
}

PHP;

        File::put("{$modulePath}/Boot.php", $content);
        $this->createdFiles[] = ['file' => 'Boot.php', 'description' => 'Event-driven module loader'];
        $this->components->task('Creating Boot.php', fn () => true);
    }

    /**
     * Resolve the namespace for the module.
     */
    protected function resolveNamespace(string $modulePath, string $name): string
    {
        if (str_contains($modulePath, 'packages/core-php/src/Mod')) {
            return "Core\\Mod\\{$name}";
        }

        return "Mod\\{$name}";
    }

    /**
     * Build the use statements for the Boot file.
     */
    protected function buildUseStatements(): string
    {
        $statements = [];

        if ($this->option('web') || $this->option('all')) {
            $statements[] = 'use Core\Events\WebRoutesRegistering;';
        }

        if ($this->option('admin') || $this->option('all')) {
            $statements[] = 'use Core\Events\AdminPanelBooting;';
        }

        if ($this->option('api') || $this->option('all')) {
            $statements[] = 'use Core\Events\ApiRoutesRegistering;';
        }

        if ($this->option('console') || $this->option('all')) {
            $statements[] = 'use Core\Events\ConsoleBooting;';
        }

        if (empty($statements)) {
            $statements[] = 'use Core\Events\WebRoutesRegistering;';
        }

        return implode("\n", $statements);
    }

    /**
     * Build the listeners array content.
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

        if ($this->option('console') || $this->option('all')) {
            $listeners[] = "        ConsoleBooting::class => 'onConsole',";
        }

        return implode("\n", $listeners);
    }

    /**
     * Check if any specific option was provided.
     */
    protected function hasAnyOption(): bool
    {
        return $this->option('web')
            || $this->option('admin')
            || $this->option('api')
            || $this->option('console')
            || $this->option('all');
    }

    /**
     * Build the handler methods.
     */
    protected function buildHandlerMethods(string $name): string
    {
        $methods = [];
        $moduleName = Str::snake($name);

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $methods[] = <<<PHP

    /**
     * Register web routes and views.
     */
    public function onWebRoutes(WebRoutesRegistering \$event): void
    {
        \$event->views('{$moduleName}', __DIR__.'/View/Blade');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            \$event->routes(fn () => require __DIR__.'/Routes/web.php');
        }
    }
PHP;
        }

        if ($this->option('admin') || $this->option('all')) {
            $methods[] = <<<PHP

    /**
     * Register admin panel components.
     */
    public function onAdminPanel(AdminPanelBooting \$event): void
    {
        // Register admin Livewire components
        // \$event->livewire('{$moduleName}.admin.index', View\Modal\Admin\Index::class);

        if (file_exists(__DIR__.'/Routes/admin.php')) {
            \$event->routes(fn () => require __DIR__.'/Routes/admin.php');
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

        if ($this->option('console') || $this->option('all')) {
            $methods[] = <<<PHP

    /**
     * Register console commands.
     */
    public function onConsole(ConsoleBooting \$event): void
    {
        // Register artisan commands
        // \$event->command(Console\Commands\ExampleCommand::class);
    }
PHP;
        }

        return implode("\n", $methods);
    }

    /**
     * Create optional files based on flags.
     */
    protected function createOptionalFiles(string $modulePath, string $name): void
    {
        $moduleName = Str::snake($name);

        if ($this->option('web') || $this->option('all') || ! $this->hasAnyOption()) {
            $this->createWebRoutes($modulePath, $moduleName);
        }

        if ($this->option('admin') || $this->option('all')) {
            $this->createAdminRoutes($modulePath, $moduleName);
        }

        if ($this->option('api') || $this->option('all')) {
            $this->createApiRoutes($modulePath, $moduleName);
        }

        // Create a sample view
        $this->createSampleView($modulePath, $moduleName);
    }

    /**
     * Create web routes file.
     */
    protected function createWebRoutes(string $modulePath, string $moduleName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} Web Routes
|--------------------------------------------------------------------------
|
| Public web routes for the {$moduleName} module.
|
*/

Route::prefix('{$moduleName}')->group(function () {
    Route::get('/', function () {
        return view('{$moduleName}::index');
    })->name('{$moduleName}.index');
});

PHP;

        File::put("{$modulePath}/Routes/web.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/web.php', 'description' => 'Public web routes'];
        $this->components->task('Creating Routes/web.php', fn () => true);
    }

    /**
     * Create admin routes file.
     */
    protected function createAdminRoutes(string $modulePath, string $moduleName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} Admin Routes
|--------------------------------------------------------------------------
|
| Admin panel routes for the {$moduleName} module.
|
*/

Route::prefix('{$moduleName}')->name('{$moduleName}.admin.')->group(function () {
    Route::get('/', function () {
        return view('{$moduleName}::admin.index');
    })->name('index');
});

PHP;

        File::put("{$modulePath}/Routes/admin.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/admin.php', 'description' => 'Admin panel routes'];
        $this->components->task('Creating Routes/admin.php', fn () => true);
    }

    /**
     * Create API routes file.
     */
    protected function createApiRoutes(string $modulePath, string $moduleName): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} API Routes
|--------------------------------------------------------------------------
|
| API routes for the {$moduleName} module.
|
*/

Route::prefix('{$moduleName}')->name('api.{$moduleName}.')->group(function () {
    Route::get('/', function () {
        return response()->json(['module' => '{$moduleName}', 'status' => 'ok']);
    })->name('index');
});

PHP;

        File::put("{$modulePath}/Routes/api.php", $content);
        $this->createdFiles[] = ['file' => 'Routes/api.php', 'description' => 'REST API routes'];
        $this->components->task('Creating Routes/api.php', fn () => true);
    }

    /**
     * Create a sample view file.
     */
    protected function createSampleView(string $modulePath, string $moduleName): void
    {
        $content = <<<BLADE
<x-layouts.app>
    <x-slot name="title">{$moduleName}</x-slot>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-4">{$moduleName} Module</h1>
        <p class="text-gray-600">Welcome to the {$moduleName} module.</p>
    </div>
</x-layouts.app>

BLADE;

        File::put("{$modulePath}/View/Blade/index.blade.php", $content);
        $this->createdFiles[] = ['file' => 'View/Blade/index.blade.php', 'description' => 'Sample index view'];
        $this->components->task('Creating View/Blade/index.blade.php', fn () => true);
    }

    /**
     * Get shell completion suggestions for arguments.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            // Suggest common module naming patterns
            $suggestions->suggestValues([
                'Auth',
                'Blog',
                'Content',
                'Dashboard',
                'Media',
                'Settings',
                'Users',
            ]);
        }
    }
}
