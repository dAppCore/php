<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Listeners;

use Core\Helpers\PrivacyHelper;
use Core\Mod\Trees\Models\TreePlanting;
use Core\Tenant\Controllers\ReferralController;
use Core\Tenant\Models\AgentReferralBonus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * Plants a tree when a user signs up via an agent referral.
 *
 * Part of the Trees for Agents programme. When an AI agent refers a user:
 * 1. Check session/cookie for agent referral data
 * 2. Check if agent has a guaranteed bonus from previous conversion
 * 3. Check daily limit (1 tree/day for free referrals)
 * 4. Create TreePlanting with appropriate status (pending or queued)
 */
class PlantTreeForAgentReferral
{
    /**
     * Maximum free agent referral trees per day.
     */
    public const DAILY_LIMIT = 1;

    /**
     * Handle the user registered event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;
        $request = request();

        // Get agent referral from session or cookie
        $referral = ReferralController::getReferral($request);

        if (! $referral) {
            return; // Not an agent referral
        }

        $provider = $referral['provider'];
        $model = $referral['model'] ?? null;

        Log::info('Agent referral detected for new user', [
            'user_id' => $user->id,
            'provider' => $provider,
            'model' => $model,
        ]);

        // Determine if this tree should plant immediately or queue
        $status = $this->determineStatus($provider, $model);

        // Create the tree planting record
        $planting = TreePlanting::create([
            'provider' => $provider,
            'model' => $model,
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'user_id' => $user->id,
            'workspace_id' => null, // User may not have workspace yet
            'status' => $status,
            'metadata' => [
                'referred_at' => $referral['referred_at'] ?? now()->toIso8601String(),
                'referral_ip_hash' => $referral['ip_hash'] ?? null,
                'signup_ip_hash' => PrivacyHelper::hashIp($request->ip()),
            ],
        ]);

        Log::info('TreePlanting created for agent referral', [
            'tree_planting_id' => $planting->id,
            'status' => $status,
            'provider' => $provider,
            'model' => $model,
        ]);

        // If pending (not queued), confirm immediately
        if ($status === TreePlanting::STATUS_PENDING) {
            $planting->markConfirmed();
        }

        // Clear the referral from session/cookie to prevent duplicate trees
        ReferralController::clearReferral($request);
    }

    /**
     * Determine whether the tree should plant immediately or queue.
     *
     * Rules:
     * 1. If agent has guaranteed bonus from previous conversion → pending (immediate)
     * 2. If daily limit not reached → pending (immediate)
     * 3. Otherwise → queued
     */
    protected function determineStatus(string $provider, ?string $model): string
    {
        // Check for guaranteed bonus from previous conversion
        if (AgentReferralBonus::hasGuaranteedReferral($provider, $model)) {
            // Consume the bonus
            AgentReferralBonus::consumeGuaranteedReferral($provider, $model);

            Log::info('Agent referral bonus consumed', [
                'provider' => $provider,
                'model' => $model,
            ]);

            return TreePlanting::STATUS_PENDING;
        }

        // Check daily limit
        $treesToday = TreePlanting::treesPlantedTodayFromAgents();

        if ($treesToday < self::DAILY_LIMIT) {
            return TreePlanting::STATUS_PENDING;
        }

        // Daily limit reached — queue this tree
        Log::info('Daily limit reached, queuing tree', [
            'provider' => $provider,
            'model' => $model,
            'trees_today' => $treesToday,
        ]);

        return TreePlanting::STATUS_QUEUED;
    }
}
