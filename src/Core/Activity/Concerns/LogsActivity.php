<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;

/**
 * Trait for models that should log activity changes.
 *
 * This trait wraps spatie/laravel-activitylog with sensible defaults for
 * the Core PHP framework, including automatic workspace_id tagging.
 *
 * Usage:
 *   class Post extends Model {
 *       use LogsActivity;
 *   }
 *
 * Configuration via model properties:
 *   - $activityLogAttributes: array of attributes to log (default: all dirty)
 *   - $activityLogName: custom log name (default: from config)
 *   - $activityLogEvents: events to log (default: ['created', 'updated', 'deleted'])
 *   - $activityLogWorkspace: whether to include workspace_id (default: true)
 *   - $activityLogOnlyDirty: only log dirty attributes (default: true)
 *
 * @requires spatie/laravel-activitylog
 */
trait LogsActivity
{
    use SpatieLogsActivity;

    /**
     * Get the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults();

        // Configure what to log
        if ($this->shouldLogOnlyDirty()) {
            $options->logOnlyDirty();
        }

        // Only log if there are actual changes
        $options->dontSubmitEmptyLogs();

        // Set log name from model property or config
        $options->useLogName($this->getActivityLogName());

        // Configure which attributes to log
        $attributes = $this->getActivityLogAttributes();
        if ($attributes !== null) {
            $options->logOnly($attributes);
        } else {
            $options->logAll();
        }

        // Configure which events to log
        $events = $this->getActivityLogEvents();
        $options->logOnlyDirty();

        // Set custom description generator
        $options->setDescriptionForEvent(fn (string $eventName) => $this->getActivityDescription($eventName));

        return $options;
    }

    /**
     * Tap into the activity before it's saved to add workspace_id.
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName): void
    {
        if ($this->shouldIncludeWorkspace()) {
            $workspaceId = $this->getActivityWorkspaceId();
            if ($workspaceId !== null) {
                $activity->properties = $activity->properties->merge([
                    'workspace_id' => $workspaceId,
                ]);
            }
        }

        // Allow further customisation in using models
        if (method_exists($this, 'customizeActivity')) {
            $this->customizeActivity($activity, $eventName);
        }
    }

    /**
     * Get the workspace ID for this activity.
     */
    protected function getActivityWorkspaceId(): ?int
    {
        // If model has workspace_id attribute, use it
        if (isset($this->workspace_id)) {
            return $this->workspace_id;
        }

        // Try to get from current workspace context
        return $this->getCurrentWorkspaceId();
    }

    /**
     * Get the current workspace ID from context.
     */
    protected function getCurrentWorkspaceId(): ?int
    {
        // First try to get from request attributes (set by middleware)
        if (request()->attributes->has('workspace_model')) {
            $workspace = request()->attributes->get('workspace_model');

            return $workspace?->id;
        }

        // Then try to get from authenticated user
        $user = auth()->user();
        if ($user && method_exists($user, 'defaultHostWorkspace')) {
            $workspace = $user->defaultHostWorkspace();

            return $workspace?->id;
        }

        return null;
    }

    /**
     * Generate a description for the activity event.
     */
    protected function getActivityDescription(string $eventName): string
    {
        $modelName = class_basename(static::class);

        return match ($eventName) {
            'created' => "Created {$modelName}",
            'updated' => "Updated {$modelName}",
            'deleted' => "Deleted {$modelName}",
            default => ucfirst($eventName)." {$modelName}",
        };
    }

    /**
     * Get the log name for this model.
     */
    protected function getActivityLogName(): string
    {
        if (property_exists($this, 'activityLogName') && $this->activityLogName) {
            return $this->activityLogName;
        }

        return config('core.activity.log_name', 'default');
    }

    /**
     * Get the attributes to log.
     *
     * @return array<string>|null Null means log all attributes
     */
    protected function getActivityLogAttributes(): ?array
    {
        if (property_exists($this, 'activityLogAttributes') && is_array($this->activityLogAttributes)) {
            return $this->activityLogAttributes;
        }

        return null;
    }

    /**
     * Get the events to log.
     *
     * @return array<string>
     */
    protected function getActivityLogEvents(): array
    {
        if (property_exists($this, 'activityLogEvents') && is_array($this->activityLogEvents)) {
            return $this->activityLogEvents;
        }

        return config('core.activity.default_events', ['created', 'updated', 'deleted']);
    }

    /**
     * Whether to include workspace_id in activity properties.
     */
    protected function shouldIncludeWorkspace(): bool
    {
        if (property_exists($this, 'activityLogWorkspace')) {
            return (bool) $this->activityLogWorkspace;
        }

        return config('core.activity.include_workspace', true);
    }

    /**
     * Whether to only log dirty (changed) attributes.
     */
    protected function shouldLogOnlyDirty(): bool
    {
        if (property_exists($this, 'activityLogOnlyDirty')) {
            return (bool) $this->activityLogOnlyDirty;
        }

        return true;
    }

    /**
     * Check if activity logging is enabled.
     */
    public static function activityLoggingEnabled(): bool
    {
        return config('core.activity.enabled', true);
    }

    /**
     * Temporarily disable activity logging for a callback.
     */
    public static function withoutActivityLogging(callable $callback): mixed
    {
        return activity()->withoutLogs($callback);
    }
}
