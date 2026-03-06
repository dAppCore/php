<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Contracts;

use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Service\ServiceVersion;

/**
 * Contract for SaaS service definitions.
 *
 * Services are the product layer of the framework - they define how modules are
 * presented to users as SaaS products. Each service has a definition that:
 *
 * - Populates the `platform_services` table for entitlement management
 * - Integrates with the admin menu system via `AdminMenuProvider`
 * - Provides versioning for API compatibility and deprecation tracking
 *
 * ## Service Lifecycle Stages
 *
 * Services progress through several lifecycle stages:
 *
 * | Stage | Description | Key Methods |
 * |-------|-------------|-------------|
 * | Discovery | Service class found in module paths | `definition()` |
 * | Validation | Dependencies checked and verified | `dependencies()`, `version()` |
 * | Initialization | Service instantiated in correct order | Constructor, DI |
 * | Runtime | Service handles requests, provides menu | `menuItems()`, `healthCheck()` |
 * | Deprecation | Service marked for removal | `version()->deprecate()` |
 * | Sunset | Service no longer available | `version()->isPastSunset()` |
 *
 * ## Service Definition Array
 *
 * The `definition()` method returns an array with service metadata:
 *
 * ```php
 * public static function definition(): array
 * {
 *     return [
 *         'code' => 'bio',                         // Unique service code
 *         'module' => 'Mod\\Bio',                  // Module namespace
 *         'name' => 'BioHost',                     // Display name
 *         'tagline' => 'Link in bio pages',        // Short description
 *         'description' => 'Create beautiful...',  // Full description
 *         'icon' => 'link',                        // FontAwesome icon
 *         'color' => '#3B82F6',                    // Brand color
 *         'entitlement_code' => 'core.srv.bio',    // Access control code
 *         'sort_order' => 10,                      // Menu ordering
 *     ];
 * }
 * ```
 *
 * ## Versioning
 *
 * Services should implement `version()` to declare their contract version.
 * This enables tracking breaking changes and deprecation:
 *
 * ```php
 * public static function version(): ServiceVersion
 * {
 *     return new ServiceVersion(2, 1, 0);
 * }
 *
 * // For deprecated services:
 * public static function version(): ServiceVersion
 * {
 *     return (new ServiceVersion(1, 0, 0))
 *         ->deprecate('Use ServiceV2 instead', new \DateTimeImmutable('2025-06-01'));
 * }
 * ```
 *
 * ## Health Monitoring
 *
 * For services that need health monitoring, also implement `HealthCheckable`:
 *
 * ```php
 * class MyService implements ServiceDefinition, HealthCheckable
 * {
 *     public function healthCheck(): HealthCheckResult
 *     {
 *         return HealthCheckResult::healthy('All systems operational');
 *     }
 * }
 * ```
 *
 *
 * @see AdminMenuProvider For menu integration
 * @see ServiceVersion For versioning
 * @see ServiceDependency For declaring dependencies
 * @see HealthCheckable For health monitoring
 * @see ServiceDiscovery For the discovery and resolution process
 */
interface ServiceDefinition extends AdminMenuProvider
{
    /**
     * Get the service definition for seeding platform_services.
     *
     * @return array{
     *     code: string,
     *     module: string,
     *     name: string,
     *     tagline?: string,
     *     description?: string,
     *     icon?: string,
     *     color?: string,
     *     entitlement_code?: string,
     *     sort_order?: int,
     * }
     */
    public static function definition(): array;

    /**
     * Get the service contract version.
     *
     * Implementations should return a ServiceVersion indicating the
     * current version of this service's contract. This is used for:
     * - Compatibility checking between service consumers and providers
     * - Deprecation tracking and sunset enforcement
     * - Migration planning when breaking changes are introduced
     *
     * Default implementation returns version 1.0.0 for backward
     * compatibility with existing services.
     */
    public static function version(): ServiceVersion;

    /**
     * Get the service dependencies.
     *
     * Declare other services that this service depends on. The framework
     * uses this information to:
     * - Validate that required dependencies are available at boot time
     * - Resolve services in correct dependency order
     * - Detect circular dependencies
     *
     * ## Example
     *
     * ```php
     * public static function dependencies(): array
     * {
     *     return [
     *         ServiceDependency::required('auth', '>=1.0.0'),
     *         ServiceDependency::optional('analytics'),
     *     ];
     * }
     * ```
     *
     * @return array<ServiceDependency>
     */
    public static function dependencies(): array;
}
