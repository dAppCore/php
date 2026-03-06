<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * Service for querying and managing activity logs.
 *
 * Provides a fluent interface for filtering activities by subject, causer,
 * workspace, event type, and more.
 *
 * Usage:
 *   // Get activities for a specific model
 *   $activities = $service->logFor($post);
 *
 *   // Get activities by a user within a workspace
 *   $activities = $service->logBy($user)->forWorkspace($workspace)->recent();
 *
 *   // Search activities
 *   $results = $service->search('updated post');
 *
 * @requires spatie/laravel-activitylog
 */
class ActivityLogService
{
    protected ?Builder $query = null;

    protected ?int $workspaceId = null;

    /**
     * Get the base activity query.
     */
    protected function newQuery(): Builder
    {
        return Activity::query()->latest();
    }

    /**
     * Get or create the current query builder.
     */
    protected function query(): Builder
    {
        if ($this->query === null) {
            $this->query = $this->newQuery();
        }

        return $this->query;
    }

    /**
     * Reset the query builder for a new chain.
     */
    public function fresh(): self
    {
        $this->query = null;
        $this->workspaceId = null;

        return $this;
    }

    /**
     * Get activities for a specific model (subject).
     *
     * @param  Model  $subject  The model to get activities for
     */
    public function logFor(Model $subject): self
    {
        $this->query()
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());

        return $this;
    }

    /**
     * Get activities performed by a specific user.
     *
     * @param  Authenticatable|Model  $causer  The user who caused the activities
     */
    public function logBy(Authenticatable|Model $causer): self
    {
        $this->query()
            ->where('causer_type', get_class($causer))
            ->where('causer_id', $causer->getKey());

        return $this;
    }

    /**
     * Scope activities to a specific workspace.
     *
     * @param  Model|int  $workspace  The workspace or workspace ID
     */
    public function forWorkspace(Model|int $workspace): self
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;
        $this->workspaceId = $workspaceId;

        $this->query()->where(function (Builder $q) use ($workspaceId) {
            $q->whereJsonContains('properties->workspace_id', $workspaceId)
                ->orWhere(function (Builder $subQ) use ($workspaceId) {
                    // Also check if subject has workspace_id
                    $subQ->whereHas('subject', function (Builder $subjectQuery) use ($workspaceId) {
                        $subjectQuery->where('workspace_id', $workspaceId);
                    });
                });
        });

        return $this;
    }

    /**
     * Filter activities by subject type.
     *
     * @param  string  $subjectType  Fully qualified class name
     */
    public function forSubjectType(string $subjectType): self
    {
        $this->query()->where('subject_type', $subjectType);

        return $this;
    }

    /**
     * Filter activities by event type.
     *
     * @param  string|array<string>  $event  Event type(s): 'created', 'updated', 'deleted', etc.
     */
    public function ofType(string|array $event): self
    {
        $events = is_array($event) ? $event : [$event];

        $this->query()->whereIn('event', $events);

        return $this;
    }

    /**
     * Filter activities by log name.
     *
     * @param  string  $logName  The log name to filter by
     */
    public function inLog(string $logName): self
    {
        $this->query()->where('log_name', $logName);

        return $this;
    }

    /**
     * Filter activities within a date range.
     */
    public function between(\DateTimeInterface|string $from, \DateTimeInterface|string|null $to = null): self
    {
        $this->query()->where('created_at', '>=', $from);

        if ($to !== null) {
            $this->query()->where('created_at', '<=', $to);
        }

        return $this;
    }

    /**
     * Filter activities from the last N days.
     *
     * @param  int  $days  Number of days
     */
    public function lastDays(int $days): self
    {
        $this->query()->where('created_at', '>=', now()->subDays($days));

        return $this;
    }

    /**
     * Search activity descriptions.
     *
     * @param  string  $query  Search query
     */
    public function search(string $query): self
    {
        $searchTerm = '%'.addcslashes($query, '%_').'%';

        $this->query()->where(function (Builder $q) use ($searchTerm) {
            $q->where('description', 'LIKE', $searchTerm)
                ->orWhere('properties', 'LIKE', $searchTerm);
        });

        return $this;
    }

    /**
     * Get recent activities with optional limit.
     *
     * @param  int  $limit  Maximum number of activities to return
     */
    public function recent(int $limit = 50): Collection
    {
        return $this->query()
            ->with(['causer', 'subject'])
            ->limit($limit)
            ->get();
    }

    /**
     * Get paginated activities.
     *
     * @param  int  $perPage  Number of activities per page
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['causer', 'subject'])
            ->paginate($perPage);
    }

    /**
     * Get all filtered activities.
     */
    public function get(): Collection
    {
        return $this->query()
            ->with(['causer', 'subject'])
            ->get();
    }

    /**
     * Get the first activity.
     */
    public function first(): ?Activity
    {
        return $this->query()
            ->with(['causer', 'subject'])
            ->first();
    }

    /**
     * Count the activities.
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Get activity statistics for a workspace.
     *
     * @return array{total: int, by_event: array, by_subject: array, by_user: array}
     */
    public function statistics(Model|int|null $workspace = null): array
    {
        $query = $this->newQuery();

        if ($workspace !== null) {
            $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;
            $query->whereJsonContains('properties->workspace_id', $workspaceId);
        }

        // Get totals by event type
        $byEvent = (clone $query)
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();

        // Get totals by subject type
        $bySubject = (clone $query)
            ->selectRaw('subject_type, COUNT(*) as count')
            ->whereNotNull('subject_type')
            ->groupBy('subject_type')
            ->pluck('count', 'subject_type')
            ->mapWithKeys(fn ($count, $type) => [class_basename($type) => $count])
            ->toArray();

        // Get top users
        $byUser = (clone $query)
            ->selectRaw('causer_id, causer_type, COUNT(*) as count')
            ->whereNotNull('causer_id')
            ->groupBy('causer_id', 'causer_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($row) {
                $causer = $row->causer;
                $name = $causer?->name ?? $causer?->email ?? "User #{$row->causer_id}";

                return [$name => $row->count];
            })
            ->toArray();

        return [
            'total' => $query->count(),
            'by_event' => $byEvent,
            'by_subject' => $bySubject,
            'by_user' => $byUser,
        ];
    }

    /**
     * Get timeline of activities grouped by date.
     *
     * @param  int  $days  Number of days to include
     */
    public function timeline(int $days = 30): \Illuminate\Support\Collection
    {
        return $this->lastDays($days)
            ->query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');
    }

    /**
     * Format an activity for display.
     *
     * @return array{
     *     id: int,
     *     event: string,
     *     description: string,
     *     timestamp: string,
     *     relative_time: string,
     *     actor: array|null,
     *     subject: array|null,
     *     changes: array|null,
     *     workspace_id: int|null
     * }
     */
    public function format(Activity $activity): array
    {
        $causer = $activity->causer;
        $subject = $activity->subject;
        $properties = $activity->properties;

        // Extract changes if available
        $changes = null;
        if ($properties->has('attributes') || $properties->has('old')) {
            $changes = [
                'old' => $properties->get('old', []),
                'new' => $properties->get('attributes', []),
            ];
        }

        return [
            'id' => $activity->id,
            'event' => $activity->event ?? 'activity',
            'description' => $activity->description,
            'timestamp' => $activity->created_at->toIso8601String(),
            'relative_time' => $activity->created_at->diffForHumans(),
            'actor' => $causer ? [
                'id' => $causer->getKey(),
                'name' => $causer->name ?? $causer->email ?? 'Unknown',
                'avatar' => method_exists($causer, 'avatarUrl') ? $causer->avatarUrl() : null,
                'initials' => $this->getInitials($causer->name ?? $causer->email ?? 'U'),
            ] : null,
            'subject' => $subject ? [
                'id' => $subject->getKey(),
                'type' => class_basename($subject),
                'name' => $this->getSubjectName($subject),
                'url' => $this->getSubjectUrl($subject),
            ] : null,
            'changes' => $changes,
            'workspace_id' => $properties->get('workspace_id'),
        ];
    }

    /**
     * Get initials from a name.
     */
    protected function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr(end($words), 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Get the display name for a subject.
     */
    protected function getSubjectName(Model $subject): string
    {
        // Try common name attributes
        foreach (['name', 'title', 'label', 'email', 'slug'] as $attribute) {
            if (isset($subject->{$attribute})) {
                return (string) $subject->{$attribute};
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    /**
     * Get the URL for a subject if available.
     */
    protected function getSubjectUrl(Model $subject): ?string
    {
        // If model has a getUrl method, use it
        if (method_exists($subject, 'getUrl')) {
            return $subject->getUrl();
        }

        // If model has a url attribute
        if (isset($subject->url)) {
            return $subject->url;
        }

        return null;
    }

    /**
     * Delete activities older than the retention period.
     *
     * @param  int|null  $days  Days to retain (null = use config)
     * @return int Number of deleted activities
     */
    public function prune(?int $days = null): int
    {
        $retentionDays = $days ?? config('core.activity.retention_days', 90);

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);

        return Activity::where('created_at', '<', $cutoffDate)->delete();
    }
}
