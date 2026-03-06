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
 * Declares that this seeder must run before the specified seeders.
 *
 * Use this attribute to ensure this seeder runs before its dependents.
 * This is the inverse of SeederAfter - it declares that other seeders
 * depend on this one.
 *
 * ## Example
 *
 * ```php
 * use Core\Tenant\Database\Seeders\PackageSeeder;
 *
 * #[SeederBefore(PackageSeeder::class)]
 * class FeatureSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 *
 * // Multiple dependents
 * #[SeederBefore(PackageSeeder::class, WorkspaceSeeder::class)]
 * class FeatureSeeder extends Seeder
 * {
 *     public function run(): void { ... }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class SeederBefore
{
    /**
     * The seeder classes that must run after this one.
     *
     * @var array<class-string>
     */
    public readonly array $seeders;

    /**
     * Create a new dependency attribute.
     *
     * @param  class-string  ...$seeders  Seeder classes that must run after this one
     */
    public function __construct(string ...$seeders)
    {
        $this->seeders = $seeders;
    }
}
