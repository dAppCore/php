<?php

declare(strict_types=1);

namespace Mod\Circular\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Test seeder with circular dependency.
 */
class CircularBSeeder extends Seeder
{
    public array $after = [
        CircularASeeder::class,
    ];

    public function run(): void
    {
        // Test seeder
    }
}
