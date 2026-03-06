<?php

declare(strict_types=1);

namespace Core\Mod\Trees\View\Modal\Web;

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreePlantingStats;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Trees for Agents public leaderboard page.
 *
 * Displays stats about trees planted through the Trees for Agents programme,
 * including provider leaderboard, model breakdown, and programme information.
 */
class Index extends Component
{
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('trees::web.index', [
            'stats' => $this->getGlobalStats(),
            'leaderboard' => $this->getProviderLeaderboard(),
            'modelStats' => $this->getModelStats(),
        ])->layout('layouts.app', [
            'title' => 'Trees for Agents | Host UK',
            'description' => 'When AI agents refer users to Host UK, we plant trees with Trees for the Future. See the leaderboard and learn how your AI can help.',
        ]);
    }

    /**
     * Get global tree planting statistics.
     */
    protected function getGlobalStats(): array
    {
        // Use confirmed and planted trees for stats
        $baseQuery = TreePlanting::whereIn('status', [
            TreePlanting::STATUS_CONFIRMED,
            TreePlanting::STATUS_PLANTED,
        ]);

        $totalTrees = (int) (clone $baseQuery)->sum('trees');

        // Get total referral visits from stats table
        $totalReferrals = (int) TreePlantingStats::query()->sum('total_referrals');

        return [
            'total_trees' => $totalTrees,
            'trees_this_month' => (int) (clone $baseQuery)->thisMonth()->sum('trees'),
            'trees_this_year' => (int) (clone $baseQuery)->thisYear()->sum('trees'),
            'total_referrals' => $totalReferrals,
            'queued_trees' => (int) TreePlanting::queued()->sum('trees'),
        ];
    }

    /**
     * Get provider leaderboard sorted by trees planted.
     */
    protected function getProviderLeaderboard(): Collection
    {
        return TreePlanting::query()
            ->selectRaw('provider, COUNT(DISTINCT user_id) as signups, SUM(trees) as trees')
            ->whereIn('status', [TreePlanting::STATUS_CONFIRMED, TreePlanting::STATUS_PLANTED])
            ->whereNotNull('provider')
            ->groupBy('provider')
            ->orderByDesc('trees')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'provider' => $item->provider,
                    'display_name' => $this->getProviderDisplayName($item->provider),
                    'trees' => (int) $item->trees,
                    'signups' => (int) $item->signups,
                ];
            });
    }

    /**
     * Get model breakdown stats across all providers.
     */
    protected function getModelStats(): Collection
    {
        return TreePlanting::query()
            ->selectRaw('provider, model, SUM(trees) as trees')
            ->whereIn('status', [TreePlanting::STATUS_CONFIRMED, TreePlanting::STATUS_PLANTED])
            ->whereNotNull('model')
            ->groupBy('provider', 'model')
            ->orderByDesc('trees')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                return [
                    'provider' => $item->provider,
                    'model' => $item->model,
                    'display_name' => $this->getModelDisplayName($item->model),
                    'trees' => (int) $item->trees,
                ];
            });
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
