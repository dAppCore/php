<?php

namespace Mod\Alpha\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Test seeder with high priority (runs first).
 */
class AlphaSeeder extends Seeder
{
    public int $priority = 10;

    public function run(): void
    {
        // Test seeder
    }
}
