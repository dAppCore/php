<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Support\ServiceProvider;

/**
 * Helpers Module Service Provider.
 *
 * Provides shared utility classes:
 * - Auth helpers (RecoveryCode, LoginRateLimiter)
 * - Privacy helpers (PrivacyHelper, HadesEncrypt)
 * - File/logging utilities (File, Log, SystemLogs)
 * - Rate limiting (RateLimit)
 * - Misc utilities (TimezoneList, UtmHelper, HorizonStatus, CommandResult)
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginRateLimiter::class);
        $this->app->singleton(HorizonStatus::class);
        $this->app->singleton(SystemLogs::class);
        $this->app->singleton(TimezoneList::class);

        $this->registerBackwardCompatAliases();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register backward compatibility class aliases.
     */
    protected function registerBackwardCompatAliases(): void
    {
        $aliases = [
            \App\Support\RecoveryCode::class => RecoveryCode::class,
            \App\Support\SystemLogs::class => SystemLogs::class,
            \App\Support\UtmHelper::class => UtmHelper::class,
            \App\Support\LoginRateLimiter::class => LoginRateLimiter::class,
            \App\Support\File::class => File::class,
            \App\Support\HorizonStatus::class => HorizonStatus::class,
            \App\Support\TimezoneList::class => TimezoneList::class,
            \App\Support\PrivacyHelper::class => PrivacyHelper::class,
            \App\Support\Log::class => Log::class,
            \App\Support\RateLimit::class => RateLimit::class,
            \App\Support\CommandResult::class => CommandResult::class,
            \App\Support\HadesEncrypt::class => HadesEncrypt::class,
        ];

        foreach ($aliases as $old => $new) {
            if (! class_exists($old)) {
                class_alias($new, $old);
            }
        }
    }
}
