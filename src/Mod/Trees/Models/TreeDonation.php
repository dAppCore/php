<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreeDonation extends Model
{
    use HasFactory;

    /**
     * Cost per tree in USD (Trees for the Future rate).
     */
    public const COST_PER_TREE = 0.25;

    protected $fillable = [
        'trees',
        'amount',
        'batch_reference',
        'donated_at',
    ];

    protected $casts = [
        'trees' => 'integer',
        'amount' => 'decimal:2',
        'donated_at' => 'datetime',
    ];

    /**
     * Create a new donation batch.
     */
    public static function createBatch(int $trees, ?string $reference = null): self
    {
        $reference ??= 'TFTF-'.now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));

        return static::create([
            'trees' => $trees,
            'amount' => $trees * self::COST_PER_TREE,
            'batch_reference' => $reference,
            'donated_at' => now(),
        ]);
    }

    /**
     * Scope to donations this month.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('donated_at', now()->month)
            ->whereYear('donated_at', now()->year);
    }

    /**
     * Scope to donations this year.
     */
    public function scopeThisYear(Builder $query): Builder
    {
        return $query->whereYear('donated_at', now()->year);
    }

    /**
     * Get the total trees donated.
     */
    public static function getTotalTreesDonated(): int
    {
        return (int) static::sum('trees');
    }

    /**
     * Get the total amount donated.
     */
    public static function getTotalAmountDonated(): float
    {
        return (float) static::sum('amount');
    }

    /**
     * Get the latest donation.
     */
    public static function getLatest(): ?self
    {
        return static::orderByDesc('donated_at')->first();
    }

    /**
     * Calculate amount for a given number of trees.
     */
    public static function calculateAmount(int $trees): float
    {
        return $trees * self::COST_PER_TREE;
    }
}
