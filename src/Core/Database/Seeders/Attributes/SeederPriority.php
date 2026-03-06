<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Database\Seeders\Attributes;

use Attribute;

/**
 * Defines the priority of a seeder.
 *
 * Lower priority values run first. Default priority is 50.
 *
 * ## Example
 *
 * ```php
 * #[SeederPriority(10)]  // Runs early
 * class FeatureSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 *
 * #[SeederPriority(90)]  // Runs later
 * class DemoSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 * ```
 *
 * ## Priority Guidelines
 *
 * - 0-20: Foundation seeders (features, configuration)
 * - 20-40: Core data (packages, workspaces)
 * - 40-60: Default priority (general seeders)
 * - 60-80: Content seeders (pages, posts)
 * - 80-100: Demo/test data seeders
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SeederPriority
{
    /**
     * Default priority for seeders without explicit priority.
     */
    public const DEFAULT = 50;

    /**
     * Create a new priority attribute.
     *
     * @param  int  $priority  Priority value (higher runs first)
     */
    public function __construct(
        public readonly int $priority
    ) {}
}
