<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Concerns;

use Core\Service\Contracts\ServiceDependency;
use Core\Service\ServiceVersion;

/**
 * Default implementation of service versioning and dependencies.
 *
 * Use this trait in ServiceDefinition implementations to provide
 * backward-compatible defaults for versioning and dependencies.
 * Override version() or dependencies() to customise.
 *
 * Example:
 * ```php
 * class MyService implements ServiceDefinition
 * {
 *     use HasServiceVersion;
 *
 *     // Uses default version 1.0.0 and no dependencies
 * }
 * ```
 *
 * Or with custom version and dependencies:
 * ```php
 * class MyService implements ServiceDefinition
 * {
 *     use HasServiceVersion;
 *
 *     public static function version(): ServiceVersion
 *     {
 *         return new ServiceVersion(2, 3, 1);
 *     }
 *
 *     public static function dependencies(): array
 *     {
 *         return [
 *             ServiceDependency::required('auth', '>=1.0.0'),
 *         ];
 *     }
 * }
 * ```
 */
trait HasServiceVersion
{
    /**
     * Get the service contract version.
     *
     * Override this method to specify a custom version or deprecation.
     */
    public static function version(): ServiceVersion
    {
        return ServiceVersion::initial();
    }

    /**
     * Get the service dependencies.
     *
     * Override this method to declare dependencies on other services.
     * By default, services have no dependencies.
     *
     * @return array<ServiceDependency>
     */
    public static function dependencies(): array
    {
        return [];
    }
}
