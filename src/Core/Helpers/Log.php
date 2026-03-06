<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Support\Facades\Log as LogFacade;

/**
 * Social module logging facade.
 *
 * Provides consistent logging interface with configurable channel
 * for social media operations and debugging.
 */
class Log
{
    /**
     * Log informational message.
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        LogFacade::stack(self::stack())->info($message, $context);
    }

    /**
     * Log error message.
     *
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        LogFacade::stack(self::stack())->error($message, $context);
    }

    /**
     * Log warning message.
     *
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        LogFacade::stack(self::stack())->warning($message, $context);
    }

    /**
     * Get log channel stack for social operations.
     *
     * @return array<int, string>
     */
    protected static function stack(): array
    {
        if ($channel = config('social.log_channel')) {
            return [$channel];
        }

        return [config('app.log_channel', 'stack')];
    }
}
