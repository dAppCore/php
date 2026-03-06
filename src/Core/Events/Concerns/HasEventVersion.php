<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events\Concerns;

/**
 * Trait for module Boot classes to declare event version compatibility.
 *
 * Use this trait in your Boot class to declare which event versions your
 * handlers support. This enables graceful handling of event API changes.
 *
 * ## Usage
 *
 * ```php
 * class Boot
 * {
 *     use HasEventVersion;
 *
 *     public static array $listens = [
 *         WebRoutesRegistering::class => 'onWebRoutes',
 *     ];
 *
 *     // Declare minimum event versions this module requires
 *     protected static array $eventVersions = [
 *         WebRoutesRegistering::class => 1,
 *     ];
 *
 *     public function onWebRoutes(WebRoutesRegistering $event): void
 *     {
 *         // Handle event
 *     }
 * }
 * ```
 *
 * ## Version Checking
 *
 * During bootstrap, the framework checks version compatibility:
 * - If a handler requires a version higher than available, a warning is logged
 * - If a handler uses a deprecated version, a deprecation notice is raised
 */
trait HasEventVersion
{
    /**
     * Get the required event version for a given event class.
     *
     * Returns the version number from $eventVersions if defined,
     * or 1 (the baseline version) if not specified.
     *
     * @param  string  $eventClass  The event class name
     * @return int The required version number
     */
    public static function getRequiredEventVersion(string $eventClass): int
    {
        if (property_exists(static::class, 'eventVersions')) {
            return static::$eventVersions[$eventClass] ?? 1;
        }

        return 1;
    }

    /**
     * Check if this module is compatible with an event version.
     *
     * @param  string  $eventClass  The event class name
     * @param  int  $availableVersion  The available event API version
     * @return bool True if the module can handle this event version
     */
    public static function isCompatibleWithEventVersion(string $eventClass, int $availableVersion): bool
    {
        $required = static::getRequiredEventVersion($eventClass);

        return $availableVersion >= $required;
    }

    /**
     * Get all declared event version requirements.
     *
     * @return array<class-string, int> Map of event class to required version
     */
    public static function getEventVersionRequirements(): array
    {
        if (property_exists(static::class, 'eventVersions')) {
            return static::$eventVersions;
        }

        return [];
    }
}
