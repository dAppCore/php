<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\View\Component;

class ActivityLog extends Component
{
    public function __construct(
        public array $items = [],
        public ?Paginator $pagination = null,
        public string $empty = 'No activity recorded yet.',
        public string $emptyIcon = 'clock',
    ) {}

    public function eventColor(string $event): string
    {
        return match ($event) {
            'created' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'updated' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'deleted' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'restored' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'login' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'logout' => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function formatTimestamp(mixed $timestamp): array
    {
        if ($timestamp instanceof Carbon) {
            return [
                'relative' => $timestamp->diffForHumans(),
                'absolute' => $timestamp->format('d M Y H:i'),
            ];
        }

        return [
            'relative' => (string) $timestamp,
            'absolute' => null,
        ];
    }

    public function formatValue(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.activity-log');
    }
}
