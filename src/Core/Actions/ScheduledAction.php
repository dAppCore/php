<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a scheduled action persisted in the database.
 *
 * @property int $id
 * @property string $action_class
 * @property string $frequency
 * @property string|null $timezone
 * @property bool $without_overlapping
 * @property bool $run_in_background
 * @property bool $is_enabled
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $next_run_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ScheduledAction extends Model
{
    protected $fillable = [
        'action_class',
        'frequency',
        'timezone',
        'without_overlapping',
        'run_in_background',
        'is_enabled',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'without_overlapping' => 'boolean',
            'run_in_background' => 'boolean',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * Scope to only enabled actions.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Parse the frequency string and return the method name.
     *
     * 'dailyAt:09:00' → 'dailyAt'
     * 'everyMinute' → 'everyMinute'
     */
    public function frequencyMethod(): string
    {
        return explode(':', $this->frequency, 2)[0];
    }

    /**
     * Parse the frequency string and return the arguments.
     *
     * 'dailyAt:09:00' → ['09:00']
     * 'weeklyOn:1,09:00' → ['1', '09:00']
     * 'everyMinute' → []
     */
    public function frequencyArgs(): array
    {
        $parts = explode(':', $this->frequency, 2);

        if (! isset($parts[1])) {
            return [];
        }

        return explode(',', $parts[1]);
    }

    /**
     * Record that this action has just run.
     */
    public function markRun(): void
    {
        $this->update(['last_run_at' => now()]);
    }
}
