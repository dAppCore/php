<?php

declare(strict_types=1);

namespace Core\Website;

use Core\Events\DomainResolving;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Mod module - Marketing sites outside the Mod structure.
 *
 * Lazy loads website providers based on incoming domain.
 * Only the matching provider is registered, isolating errors
 * so one broken website doesn't take down all others.
 *
 * Mod modules use the same $listens pattern as Mod modules,
 * listening for DomainResolving to register themselves.
 */
class Boot extends ServiceProvider
{
    /**
     * The currently matched website provider class (if any).
     */
    protected ?string $matchedProvider = null;

    public function register(): void
    {
        $this->app->singleton(DomainResolver::class);

        // CLI (artisan, tests, queues) - load all providers
        if ($this->app->runningInConsole()) {
            $this->registerAllProviders();

            return;
        }

        // Web request - fire DomainResolving event to find matching provider
        $host = $_SERVER['HTTP_HOST'] ?? null;

        if ($host) {
            $event = new DomainResolving($host);
            Event::dispatch($event);

            if ($provider = $event->matchedProvider()) {
                $this->matchedProvider = $provider;
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        // Event listeners are wired by LifecycleEventProvider/ModuleRegistry
        // Mod modules' handlers fire via the standard event system
    }

    /**
     * Register all website providers (for CLI).
     *
     * Scans app/Mod for Boot.php files and registers them.
     */
    protected function registerAllProviders(): void
    {
        $websitePath = $this->app->basePath('app/Mod');

        if (! is_dir($websitePath)) {
            return;
        }

        foreach (glob("{$websitePath}/*/Boot.php") as $file) {
            $relative = str_replace([$websitePath.'/', '/Boot.php'], '', $file);
            $class = "Mod\\{$relative}\\Boot";

            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }
}
