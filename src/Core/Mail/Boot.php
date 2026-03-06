<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Mail Module Service Provider.
 *
 * Provides email validation functionality:
 * - Disposable email detection
 * - Email format validation
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EmailShield::class);

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
        // EmailShield alias for App\Services\Email namespace
        if (! class_exists(\App\Services\Email\EmailShield::class)) {
            class_alias(
                EmailShield::class,
                \App\Services\Email\EmailShield::class
            );
        }

        // EmailValidationResult alias for App\Services\Email namespace
        if (! class_exists(\App\Services\Email\EmailValidationResult::class)) {
            class_alias(
                EmailValidationResult::class,
                \App\Services\Email\EmailValidationResult::class
            );
        }
    }
}
