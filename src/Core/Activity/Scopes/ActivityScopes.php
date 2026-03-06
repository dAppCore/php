<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\Scopes;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Query scopes for the Activity model.
 *
 * These scopes can be added to a custom Activity model that extends
 * Spatie's Activity model, or used as standalone scope methods.
 *
 * Usage with custom Activity model:
 *   class Activity extends \Spatie\Activitylog\Models\Activity {
 *       use ActivityScopes;
 *   }
 *
 * Usage as standalone scopes:
 *   Activity::forWorkspace($workspaceId)->get();
 *   Activity::forSubject($post)->ofType('updated')->get();
 *
 * @requires spatie/laravel-activitylog
 */
trait ActivityScopes
{
    /**
     * Scope activities to a specific workspace.
     *
     * Filters activities where either:
     * - The workspace_id is stored in properties
     * - The subject model has the given workspace_id
     *
     * @param  Model|int  $workspace  Workspace model or ID
     */
    public function scopeForWorkspace(Builder $query, Model|int $workspace): Builder
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

        return $query->where(function (Builder $q) use ($workspaceId) {
            // Check properties->workspace_id
            $q->whereJsonContains('properties->workspace_id', $workspaceId);

            // Or check if subject has workspace_id
            $q->orWhereHasMorph(
                'subject',
                '*',
                fn (Builder $subjectQuery) => $subjectQuery->where('workspace_id', $workspaceId)
            );
        });
    }

    /**
     * Scope activities to a specific subject model.
     *
     * @param  Model  $subject  The subject model instance
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope activities to a specific subject type.
     *
     * @param  string  $subjectType  Fully qualified class name
     */
    public function scopeForSubjectType(Builder $query, string $subjectType): Builder
    {
        return $query->where('subject_type', $subjectType);
    }

    /**
     * Scope activities by the causer (user who performed the action).
     *
     * @param  Authenticatable|Model  $user  The causer model
     */
    public function scopeByCauser(Builder $query, Authenticatable|Model $user): Builder
    {
        return $query
            ->where('causer_type', get_class($user))
            ->where('causer_id', $user->getKey());
    }

    /**
     * Scope activities by causer ID (when you don't have the model).
     *
     * @param  int  $causerId  The causer's primary key
     * @param  string|null  $causerType  Optional causer type (defaults to User model)
     */
    public function scopeByCauserId(Builder $query, int $causerId, ?string $causerType = null): Builder
    {
        $query->where('causer_id', $causerId);

        if ($causerType !== null) {
            $query->where('causer_type', $causerType);
        }

        return $query;
    }

    /**
     * Scope activities by event type.
     *
     * @param  string|array<string>  $event  Event type(s): 'created', 'updated', 'deleted'
     */
    public function scopeOfType(Builder $query, string|array $event): Builder
    {
        $events = is_array($event) ? $event : [$event];

        return $query->whereIn('event', $events);
    }

    /**
     * Scope to only created events.
     */
    public function scopeCreatedEvents(Builder $query): Builder
    {
        return $query->where('event', 'created');
    }

    /**
     * Scope to only updated events.
     */
    public function scopeUpdatedEvents(Builder $query): Builder
    {
        return $query->where('event', 'updated');
    }

    /**
     * Scope to only deleted events.
     */
    public function scopeDeletedEvents(Builder $query): Builder
    {
        return $query->where('event', 'deleted');
    }

    /**
     * Scope activities within a date range.
     *
     * @param  \DateTimeInterface|string  $from  Start date
     * @param  \DateTimeInterface|string|null  $to  End date (optional)
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string|null $to = null): Builder
    {
        $query->where('created_at', '>=', $from);

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope activities from today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    /**
     * Scope activities from the last N days.
     *
     * @param  int  $days  Number of days
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope activities from the last N hours.
     *
     * @param  int  $hours  Number of hours
     */
    public function scopeLastHours(Builder $query, int $hours): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Search activities by description.
     *
     * @param  string  $search  Search term
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.addcslashes($search, '%_').'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'LIKE', $term)
                ->orWhere('properties', 'LIKE', $term);
        });
    }

    /**
     * Scope to activities in a specific log.
     *
     * @param  string  $logName  The log name
     */
    public function scopeInLog(Builder $query, string $logName): Builder
    {
        return $query->where('log_name', $logName);
    }

    /**
     * Scope to activities with changes (non-empty properties).
     */
    public function scopeWithChanges(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereJsonLength('properties->attributes', '>', 0)
                ->orWhereJsonLength('properties->old', '>', 0);
        });
    }

    /**
     * Scope to activities for models that still exist.
     */
    public function scopeWithExistingSubject(Builder $query): Builder
    {
        return $query->whereHas('subject');
    }

    /**
     * Scope to activities for models that have been deleted.
     */
    public function scopeWithDeletedSubject(Builder $query): Builder
    {
        return $query->whereDoesntHave('subject');
    }

    /**
     * Order by newest first.
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->latest('created_at');
    }

    /**
     * Order by oldest first.
     */
    public function scopeOldest(Builder $query): Builder
    {
        return $query->oldest('created_at');
    }
}
