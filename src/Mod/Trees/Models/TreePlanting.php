<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Models;

use Core\Tenant\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreePlanting extends Model
{
    use BelongsToWorkspace, HasFactory;

    /**
     * Source constants.
     */
    public const SOURCE_AGENT_REFERRAL = 'agent_referral';

    public const SOURCE_SUBSCRIPTION = 'subscription';

    public const SOURCE_DIRECT = 'direct';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PLANTED = 'planted';

    /**
     * Valid providers for agent referrals.
     */
    public const VALID_PROVIDERS = [
        'anthropic',
        'openai',
        'google',
        'meta',
        'mistral',
        'local',
        'unknown',
    ];

    protected $fillable = [
        'provider',
        'model',
        'source',
        'trees',
        'user_id',
        'workspace_id',
        'tftf_reference',
        'status',
        'metadata',
        'created_at',  // Allows setting historical dates for imports/tests
    ];

    protected $casts = [
        'trees' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the user this planting is associated with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to plantings from agent referrals.
     */
    public function scopeForAgent(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_AGENT_REFERRAL);
    }

    /**
     * Scope to a specific provider.
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to a specific model.
     */
    public function scopeByModel(Builder $query, string $model): Builder
    {
        return $query->where('model', $model);
    }

    /**
     * Scope to queued plantings.
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    /**
     * Scope to pending plantings.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to confirmed plantings.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope to planted (donated to TFTF).
     */
    public function scopePlanted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANTED);
    }

    /**
     * Scope to subscription-based plantings.
     */
    public function scopeFromSubscription(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_SUBSCRIPTION);
    }

    /**
     * Scope to this month's plantings.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Scope to this year's plantings.
     */
    public function scopeThisYear(Builder $query): Builder
    {
        return $query->whereYear('created_at', now()->year);
    }

    /**
     * Scope to today's plantings.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Check if this planting is from an agent referral.
     */
    public function isAgentReferral(): bool
    {
        return $this->source === self::SOURCE_AGENT_REFERRAL;
    }

    /**
     * Check if this planting is from a subscription.
     */
    public function isSubscription(): bool
    {
        return $this->source === self::SOURCE_SUBSCRIPTION;
    }

    /**
     * Check if this planting is queued.
     */
    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    /**
     * Check if this planting is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if this planting has been donated to TFTF.
     */
    public function isPlanted(): bool
    {
        return $this->status === self::STATUS_PLANTED;
    }

    /**
     * Mark this planting as confirmed and decrement tree reserve.
     *
     * If the reserve is depleted, the planting will be queued instead.
     */
    public function markConfirmed(): self
    {
        // Check if reserve has available trees
        if (! TreeReserve::hasAvailable($this->trees)) {
            // Queue if reserve depleted
            if (! $this->isQueued()) {
                $this->update(['status' => self::STATUS_QUEUED]);
            }

            return $this;
        }

        // Decrement the reserve
        TreeReserve::decrementReserve($this->trees);

        // Update status to confirmed
        $this->update(['status' => self::STATUS_CONFIRMED]);

        // Update stats
        TreePlantingStats::incrementOrCreate(
            $this->provider ?? 'unknown',
            $this->model,
            $this->trees,
            $this->source === self::SOURCE_AGENT_REFERRAL ? 1 : 0
        );

        return $this;
    }

    /**
     * Mark this planting as planted (part of TFTF donation).
     */
    public function markPlanted(string $batchReference): self
    {
        $this->update([
            'status' => self::STATUS_PLANTED,
            'tftf_reference' => $batchReference,
        ]);

        return $this;
    }

    /**
     * Get the oldest queued planting.
     */
    public static function oldestQueued(): ?self
    {
        return static::queued()
            ->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Count trees planted today from agent referrals.
     */
    public static function treesPlantedTodayFromAgents(): int
    {
        return (int) static::forAgent()
            ->today()
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PLANTED])
            ->sum('trees');
    }

    /**
     * Validate a provider name.
     */
    public static function isValidProvider(string $provider): bool
    {
        return in_array($provider, self::VALID_PROVIDERS, true);
    }
}
