<?php

namespace Mod\Beta\Database\Seeders;

use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederPriority;
use Illuminate\Database\Seeder;
use Mod\Alpha\Database\Seeders\AlphaSeeder;

/**
 * Test seeder with attribute-based configuration.
 */
#[SeederPriority(50)]
#[SeederAfter(AlphaSeeder::class)]
class BetaSeeder extends Seeder
{
    public function run(): void
    {
        // Test seeder
    }
}
