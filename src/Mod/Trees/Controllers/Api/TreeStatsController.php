<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Controllers\Api;

use Core\Front\Controller;
use Core\Mod\Trees\Models\TreePlanting;
use Illuminate\Http\JsonResponse;

/**
 * Public API for Trees for Agents statistics.
 *
 * Part of the Trees for Agents programme. These endpoints are public (no auth)
 * and allow AI agents to check global stats, provider rankings, and their
 * own impact on tree planting.
 */
class TreeStatsController extends Controller
{
    /**
     * Get global tree planting statistics.
     *
     * Returns total trees planted, this month, this year, families supported,
     * and current queue size.
     */
    public function index(): JsonResponse
    {
        $baseQuery = TreePlanting::whereIn('status', [
            TreePlanting::STATUS_CONFIRMED,
            TreePlanting::STATUS_PLANTED,
        ]);

        $totalTrees = (int) (clone $baseQuery)->sum('trees');

        return response()->json([
            'success' => true,
            'stats' => [
                'total_trees' => $totalTrees,
                'trees_this_month' => (int) (clone $baseQuery)->thisMonth()->sum('trees'),
                'trees_this_year' => (int) (clone $baseQuery)->thisYear()->sum('trees'),
                'families_supported' => (int) floor($totalTrees / 2500),
                'queued_trees' => (int) TreePlanting::queued()->sum('trees'),
            ],
            'links' => [
                'leaderboard' => url('/trees'),
                'programme_info' => url('/trees#about'),
                'for_agents' => url('/trees#for-agents'),
            ],
        ]);
    }

    /**
     * Get statistics for a specific provider.
     *
     * Returns provider totals and model breakdown.
     */
    public function provider(string $provider): JsonResponse
    {
        // Validate provider
        if (! TreePlanting::isValidProvider($provider)) {
            return response()->json([
                'success' => false,
                'error' => "Unknown provider: {$provider}",
                'valid_providers' => TreePlanting::VALID_PROVIDERS,
            ], 404);
        }

        $baseQuery = TreePlanting::whereIn('status', [
            TreePlanting::STATUS_CONFIRMED,
            TreePlanting::STATUS_PLANTED,
        ])->byProvider($provider);

        $totalTrees = (int) (clone $baseQuery)->sum('trees');
        $totalSignups = (int) (clone $baseQuery)->count();

        // Get model breakdown for this provider
        $models = TreePlanting::query()
            ->selectRaw('model, SUM(trees) as trees, COUNT(*) as signups')
            ->whereIn('status', [TreePlanting::STATUS_CONFIRMED, TreePlanting::STATUS_PLANTED])
            ->byProvider($provider)
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('trees')
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'display_name' => $this->getModelDisplayName($row->model),
                'trees' => (int) $row->trees,
                'signups' => (int) $row->signups,
            ]);

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'display_name' => $this->getProviderDisplayName($provider),
            'stats' => [
                'total_trees' => $totalTrees,
                'total_signups' => $totalSignups,
                'trees_this_month' => (int) (clone $baseQuery)->thisMonth()->sum('trees'),
                'trees_this_year' => (int) (clone $baseQuery)->thisYear()->sum('trees'),
            ],
            'models' => $models,
            'referral_url' => url("/ref/{$provider}"),
        ]);
    }

    /**
     * Get statistics for a specific model.
     *
     * Returns model-specific stats with referral URL.
     */
    public function model(string $provider, string $model): JsonResponse
    {
        // Validate provider
        if (! TreePlanting::isValidProvider($provider)) {
            return response()->json([
                'success' => false,
                'error' => "Unknown provider: {$provider}",
                'valid_providers' => TreePlanting::VALID_PROVIDERS,
            ], 404);
        }

        $baseQuery = TreePlanting::whereIn('status', [
            TreePlanting::STATUS_CONFIRMED,
            TreePlanting::STATUS_PLANTED,
        ])->byProvider($provider)->byModel($model);

        $totalTrees = (int) (clone $baseQuery)->sum('trees');
        $totalSignups = (int) (clone $baseQuery)->count();

        // If no trees found for this model, return 404
        if ($totalTrees === 0 && $totalSignups === 0) {
            return response()->json([
                'success' => false,
                'error' => "No trees planted by {$provider}/{$model} yet",
                'hint' => 'Start planting by referring users via your referral URL',
                'referral_url' => url("/ref/{$provider}/{$model}"),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'model' => $model,
            'display_name' => $this->getModelDisplayName($model),
            'stats' => [
                'total_trees' => $totalTrees,
                'total_signups' => $totalSignups,
                'trees_this_month' => (int) (clone $baseQuery)->thisMonth()->sum('trees'),
                'trees_this_year' => (int) (clone $baseQuery)->thisYear()->sum('trees'),
            ],
            'referral_url' => url("/ref/{$provider}/{$model}"),
            'leaderboard_url' => url('/trees'),
        ]);
    }

    /**
     * Get the provider leaderboard.
     *
     * Returns top 20 providers ranked by trees planted.
     */
    public function leaderboard(): JsonResponse
    {
        $leaderboard = TreePlanting::query()
            ->selectRaw('provider, SUM(trees) as trees, COUNT(DISTINCT user_id) as signups')
            ->whereIn('status', [TreePlanting::STATUS_CONFIRMED, TreePlanting::STATUS_PLANTED])
            ->whereNotNull('provider')
            ->groupBy('provider')
            ->orderByDesc('trees')
            ->limit(20)
            ->get()
            ->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'provider' => $row->provider,
                'display_name' => $this->getProviderDisplayName($row->provider),
                'trees' => (int) $row->trees,
                'signups' => (int) $row->signups,
            ]);

        // Get global total for context
        $totalTrees = (int) TreePlanting::whereIn('status', [
            TreePlanting::STATUS_CONFIRMED,
            TreePlanting::STATUS_PLANTED,
        ])->sum('trees');

        return response()->json([
            'success' => true,
            'total_trees' => $totalTrees,
            'leaderboard' => $leaderboard,
            'links' => [
                'full_leaderboard' => url('/trees'),
                'programme_info' => url('/trees#about'),
            ],
        ]);
    }

    /**
     * Get display name for a provider.
     */
    protected function getProviderDisplayName(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'Anthropic',
            'openai' => 'OpenAI',
            'google' => 'Google',
            'meta' => 'Meta',
            'mistral' => 'Mistral',
            'local' => 'Local Models',
            'unknown' => 'Unknown Agents',
            default => ucfirst($provider),
        };
    }

    /**
     * Get display name for a model.
     */
    protected function getModelDisplayName(string $model): string
    {
        return match (strtolower($model)) {
            'claude-opus', 'claude-opus-4' => 'Claude Opus',
            'claude-sonnet', 'claude-sonnet-4' => 'Claude Sonnet',
            'claude-haiku', 'claude-haiku-3' => 'Claude Haiku',
            'gpt-4', 'gpt-4o', 'gpt-4-turbo' => 'GPT-4',
            'gpt-3.5', 'gpt-3.5-turbo' => 'GPT-3.5',
            'o1', 'o1-preview', 'o1-mini' => 'o1',
            'gemini-pro', 'gemini-1.5-pro' => 'Gemini Pro',
            'gemini-ultra', 'gemini-1.5-ultra' => 'Gemini Ultra',
            'gemini-flash', 'gemini-1.5-flash' => 'Gemini Flash',
            'llama-3', 'llama-3.1', 'llama-3.2' => 'LLaMA 3',
            default => $model,
        };
    }
}
