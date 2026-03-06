<?php

namespace Mod\Circular\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Test seeder with circular dependency.
 */
class CircularASeeder extends Seeder
{
    public array $after = [
        CircularBSeeder::class,
    ];

    public function run(): void
    {
        // Test seeder
    }
}
