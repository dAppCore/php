<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\View\Modal\Admin;

use Core\Activity\Services\ActivityLogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Livewire component for displaying activity logs in the admin panel.
 *
 * Features:
 * - Paginated activity list
 * - Filters: user, model type, event type, date range
 * - Activity detail modal with full diff
 * - Optional polling for real-time updates
 *
 * Usage:
 *   <livewire:core.activity-feed />
 *   <livewire:core.activity-feed :workspace-id="$workspace->id" />
 *   <livewire:core.activity-feed poll="10s" />
 */
class ActivityFeed extends Component
{
    use WithPagination;

    /**
     * Filter by workspace ID.
     */
    public ?int $workspaceId = null;

    /**
     * Filter by causer (user) ID.
     */
    #[Url]
    public ?int $causerId = null;

    /**
     * Filter by subject type (model class basename).
     */
    #[Url]
    public string $subjectType = '';

    /**
     * Filter by event type.
     */
    #[Url]
    public string $eventType = '';

    /**
     * Filter by date range (days back).
     */
    #[Url]
    public int $daysBack = 30;

    /**
     * Search query.
     */
    #[Url]
    public string $search = '';

    /**
     * Currently selected activity for detail view.
     */
    public ?int $selectedActivityId = null;

    /**
     * Whether to show the detail modal.
     */
    public bool $showDetailModal = false;

    /**
     * Polling interval in seconds (0 = disabled).
     */
    public int $pollInterval = 0;

    /**
     * Number of items per page.
     */
    public int $perPage = 15;

    protected ActivityLogService $activityService;

    public function boot(ActivityLogService $activityService): void
    {
        $this->activityService = $activityService;
    }

    public function mount(?int $workspaceId = null, int $pollInterval = 0, int $perPage = 15): void
    {
        $this->workspaceId = $workspaceId;
        $this->pollInterval = $pollInterval;
        $this->perPage = $perPage;
    }

    /**
     * Get available subject types for filtering.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function subjectTypes(): array
    {
        $types = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn ($type) => [class_basename($type) => class_basename($type)])
            ->toArray();

        return ['' => 'All Types'] + $types;
    }

    /**
     * Get available event types for filtering.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function eventTypes(): array
    {
        return [
            '' => 'All Events',
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
        ];
    }

    /**
     * Get available users (causers) for filtering.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function causers(): array
    {
        $causers = Activity::query()
            ->whereNotNull('causer_id')
            ->with('causer')
            ->distinct()
            ->get()
            ->mapWithKeys(function ($activity) {
                $causer = $activity->causer;
                if (! $causer) {
                    return [];
                }
                $name = $causer->name ?? $causer->email ?? "User #{$causer->getKey()}";

                return [$causer->getKey() => $name];
            })
            ->filter()
            ->toArray();

        return ['' => 'All Users'] + $causers;
    }

    /**
     * Get date range options.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function dateRanges(): array
    {
        return [
            1 => 'Last 24 hours',
            7 => 'Last 7 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days',
            365 => 'Last year',
        ];
    }

    /**
     * Get paginated activities.
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        $service = $this->activityService->fresh();

        // Apply workspace filter
        if ($this->workspaceId) {
            $service->forWorkspace($this->workspaceId);
        }

        // Apply causer filter
        if ($this->causerId) {
            // We need to work around the service's user expectation
            $service->query()->where('causer_id', $this->causerId);
        }

        // Apply subject type filter
        if ($this->subjectType) {
            // Find the full class name that matches the basename
            $fullType = Activity::query()
                ->where('subject_type', 'LIKE', '%\\'.$this->subjectType)
                ->orWhere('subject_type', $this->subjectType)
                ->value('subject_type');

            if ($fullType) {
                $service->forSubjectType($fullType);
            }
        }

        // Apply event type filter
        if ($this->eventType) {
            $service->ofType($this->eventType);
        }

        // Apply date range
        $service->lastDays($this->daysBack);

        // Apply search
        if ($this->search) {
            $service->search($this->search);
        }

        return $service->paginate($this->perPage);
    }

    /**
     * Get the selected activity for the detail modal.
     */
    #[Computed]
    public function selectedActivity(): ?Activity
    {
        if (! $this->selectedActivityId) {
            return null;
        }

        return Activity::with(['causer', 'subject'])->find($this->selectedActivityId);
    }

    /**
     * Get activity statistics.
     *
     * @return array{total: int, by_event: array, by_subject: array}
     */
    #[Computed]
    public function statistics(): array
    {
        return $this->activityService->statistics($this->workspaceId);
    }

    /**
     * Show the detail modal for an activity.
     */
    public function showDetail(int $activityId): void
    {
        $this->selectedActivityId = $activityId;
        $this->showDetailModal = true;
    }

    /**
     * Close the detail modal.
     */
    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedActivityId = null;
    }

    /**
     * Reset all filters.
     */
    public function resetFilters(): void
    {
        $this->causerId = null;
        $this->subjectType = '';
        $this->eventType = '';
        $this->daysBack = 30;
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Handle filter changes by resetting pagination.
     */
    public function updatedCauserId(): void
    {
        $this->resetPage();
    }

    public function updatedSubjectType(): void
    {
        $this->resetPage();
    }

    public function updatedEventType(): void
    {
        $this->resetPage();
    }

    public function updatedDaysBack(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
    public function formatActivity(Activity $activity): array
    {
        return $this->activityService->format($activity);
    }

    /**
     * Get the event color class.
     */
    public function eventColor(string $event): string
    {
        return match ($event) {
            'created' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'updated' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get the event icon.
     */
    public function eventIcon(string $event): string
    {
        return match ($event) {
            'created' => 'plus-circle',
            'updated' => 'pencil',
            'deleted' => 'trash',
            default => 'clock',
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('core.activity::admin.activity-feed');
    }
}
