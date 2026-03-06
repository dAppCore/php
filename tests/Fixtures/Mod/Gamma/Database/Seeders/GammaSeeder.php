<?php

namespace Mod\Gamma\Database\Seeders;

use Illuminate\Database\Seeder;
use Mod\Beta\Database\Seeders\BetaSeeder;

/**
 * Test seeder with property-based dependency.
 */
class GammaSeeder extends Seeder
{
    public int $priority = 90;

    public array $after = [
        BetaSeeder::class,
    ];

    public function run(): void
    {
        // Test seeder
    }
}
