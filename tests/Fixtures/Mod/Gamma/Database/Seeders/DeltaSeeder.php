<?php

namespace Mod\Gamma\Database\Seeders;

use Core\Database\Seeders\Attributes\SeederBefore;
use Illuminate\Database\Seeder;
use Mod\Beta\Database\Seeders\BetaSeeder;

/**
 * Test seeder with before dependency.
 */
#[SeederBefore(BetaSeeder::class)]
class DeltaSeeder extends Seeder
{
    public int $priority = 20;

    public function run(): void
    {
        // Test seeder
    }
}
