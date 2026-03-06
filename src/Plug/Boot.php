<?php

declare(strict_types=1);

namespace Core\Plug;

use Illuminate\Support\ServiceProvider;

/**
 * Plug Module Service Provider.
 *
 * Social network integrations with self-describing, operation-based architecture.
 * Each provider is split into discrete operations (Auth, Post, Delete, Media, etc.).
 *
 * Usage:
 *   use Core\Plug\Social\Twitter\Auth;
 *   use Core\Plug\Social\Twitter\Post;
 *
 *   $auth = new Auth($clientId, $clientSecret, $redirectUrl);
 *   $post = (new Post())->withToken($token);
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Registry::class, function () {
            $registry = new Registry;
            $registry->discover();

            return $registry;
        });

        $this->app->alias(Registry::class, 'plug.registry');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Pure library module - no routes, views, or migrations
    }
}
