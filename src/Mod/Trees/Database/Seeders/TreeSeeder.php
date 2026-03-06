<?php

namespace Core\Mod\Trees\Database\Seeders;

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreePlantingStats;
use Core\Mod\Trees\Models\TreeReserve;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TreeSeeder extends Seeder
{
    /**
     * Seed tree planting data for development and demo.
     *
     * Creates sample plantings from various AI providers and initialises
     * the tree reserve. The data shows a realistic distribution of trees
     * planted via agent referrals.
     */
    public function run(): void
    {
        if (! Schema::hasTable('tree_plantings')) {
            return;
        }

        // Ensure tree reserve exists (creates singleton with 695 trees)
        TreeReserve::instance();

        // Sample plantings from various AI agents over the past months
        $plantings = [
            // Anthropic models
            ['provider' => 'anthropic', 'model' => 'claude-opus-4', 'trees' => 42, 'days_ago' => 30],
            ['provider' => 'anthropic', 'model' => 'claude-opus-4', 'trees' => 28, 'days_ago' => 14],
            ['provider' => 'anthropic', 'model' => 'claude-opus-4', 'trees' => 15, 'days_ago' => 3],
            ['provider' => 'anthropic', 'model' => 'claude-sonnet-4', 'trees' => 35, 'days_ago' => 25],
            ['provider' => 'anthropic', 'model' => 'claude-sonnet-4', 'trees' => 22, 'days_ago' => 10],
            ['provider' => 'anthropic', 'model' => 'claude-haiku-3', 'trees' => 18, 'days_ago' => 20],

            // OpenAI models
            ['provider' => 'openai', 'model' => 'gpt-4o', 'trees' => 25, 'days_ago' => 28],
            ['provider' => 'openai', 'model' => 'gpt-4o', 'trees' => 12, 'days_ago' => 7],
            ['provider' => 'openai', 'model' => 'o1', 'trees' => 8, 'days_ago' => 5],

            // Google models
            ['provider' => 'google', 'model' => 'gemini-1.5-pro', 'trees' => 15, 'days_ago' => 22],
            ['provider' => 'google', 'model' => 'gemini-1.5-flash', 'trees' => 10, 'days_ago' => 12],

            // Local models
            ['provider' => 'local', 'model' => 'llama-3.2', 'trees' => 5, 'days_ago' => 18],

            // Unknown agents
            ['provider' => 'unknown', 'model' => null, 'trees' => 3, 'days_ago' => 15],
        ];

        foreach ($plantings as $data) {
            $createdAt = Carbon::now()->subDays($data['days_ago']);

            // Create the planting record
            TreePlanting::create([
                'provider' => $data['provider'],
                'model' => $data['model'],
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => $data['trees'],
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Update stats for the date
            TreePlantingStats::incrementOrCreate(
                $data['provider'],
                $data['model'],
                trees: $data['trees'],
                signups: 1,
                referrals: rand(1, 5),
                date: $createdAt
            );
        }

        $this->command->info('Seeded '.count($plantings).' tree planting records.');
        $this->command->info('Total trees: '.array_sum(array_column($plantings, 'trees')));
    }
}
