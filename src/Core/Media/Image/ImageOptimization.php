<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Image;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Image optimisation record.
 *
 * @property int $id
 * @property string $path
 * @property string|null $original_path
 * @property int $original_size
 * @property int $optimized_size
 * @property int $percentage_saved
 * @property string|null $driver
 * @property int|null $quality
 * @property int|null $workspace_id
 * @property string|null $optimizable_type
 * @property int|null $optimizable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ImageOptimization extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'original_path',
        'original_size',
        'optimized_size',
        'percentage_saved',
        'driver',
        'quality',
        'workspace_id',
        'optimizable_type',
        'optimizable_id',
    ];

    protected $casts = [
        'original_size' => 'integer',
        'optimized_size' => 'integer',
        'percentage_saved' => 'integer',
        'quality' => 'integer',
    ];

    /**
     * The workspace this optimization belongs to.
     *
     * Returns a relationship to the Workspace model if it exists,
     * otherwise returns null.
     */
    public function workspace(): ?BelongsTo
    {
        if (! class_exists('Core\\Mod\\Tenant\\Models\\Workspace')) {
            return null;
        }

        return $this->belongsTo('Core\\Mod\\Tenant\\Models\\Workspace');
    }

    /**
     * Get the parent optimizable model (Page, User, etc.).
     */
    public function optimizable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to a specific workspace.
     *
     * @param  Model|null  $workspace  The workspace model to filter by
     */
    public function scopeForWorkspace($query, ?Model $workspace)
    {
        if ($workspace === null) {
            return $query;
        }

        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Get human-readable savings summary.
     *
     * Example: "45% saved (120KB → 66KB)"
     */
    public function getSavingsHumanAttribute(): string
    {
        $originalKb = round($this->original_size / 1024, 1);
        $optimizedKb = round($this->optimized_size / 1024, 1);

        // Format with appropriate unit
        $original = $this->formatBytes($this->original_size);
        $optimized = $this->formatBytes($this->optimized_size);

        return sprintf(
            '%d%% saved (%s → %s)',
            $this->percentage_saved,
            $original,
            $optimized
        );
    }

    /**
     * Get human-readable size saved.
     */
    public function getSizeSavedHumanAttribute(): string
    {
        $saved = $this->original_size - $this->optimized_size;

        return $this->formatBytes($saved);
    }

    /**
     * Format bytes to human-readable size.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).'GB';
    }

    /**
     * Get total savings statistics for a workspace.
     *
     * @param  Model|null  $workspace  Optional workspace model to filter by
     */
    public static function getWorkspaceStats(?Model $workspace = null): array
    {
        $query = static::query();

        if ($workspace) {
            $query->where('workspace_id', $workspace->id);
        }

        $optimizations = $query->get();

        $totalOriginal = $optimizations->sum('original_size');
        $totalOptimized = $optimizations->sum('optimized_size');
        $totalSaved = $totalOriginal - $totalOptimized;

        $averagePercentage = $optimizations->count() > 0
            ? $optimizations->avg('percentage_saved')
            : 0;

        return [
            'count' => $optimizations->count(),
            'total_original' => $totalOriginal,
            'total_optimized' => $totalOptimized,
            'total_saved' => $totalSaved,
            'average_percentage' => round($averagePercentage, 1),
            'total_saved_human' => static::formatBytesStatic($totalSaved),
        ];
    }

    /**
     * Static version of formatBytes for use in getWorkspaceStats.
     */
    public static function formatBytesStatic(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).'GB';
    }
}
