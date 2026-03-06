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
 * Declares that this seeder must run after the specified seeders.
 *
 * Use this attribute to define explicit dependencies between seeders.
 * The seeder will not run until all specified dependencies have completed.
 *
 * ## Example
 *
 * ```php
 * use Core\Tenant\Database\Seeders\FeatureSeeder;
 *
 * #[SeederAfter(FeatureSeeder::class)]
 * class PackageSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 *
 * // Multiple dependencies
 * #[SeederAfter(FeatureSeeder::class, PackageSeeder::class)]
 * class WorkspaceSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class SeederAfter
{
    /**
     * The seeder classes that must run before this one.
     *
     * @var array<class-string>
     */
    public readonly array $seeders;

    /**
     * Create a new dependency attribute.
     *
     * @param  class-string  ...$seeders  Seeder classes that must run first
     */
    public function __construct(string ...$seeders)
    {
        $this->seeders = $seeders;
    }
}
