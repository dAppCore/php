<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

/**
 * Fired when search functionality is requested.
 *
 * Modules listen to this event to register searchable models or search
 * providers. This enables lazy loading of search indexing dependencies
 * until search is actually needed.
 *
 * ## When This Event Fires
 *
 * Fired when the search system initializes, typically when a search
 * query is performed or search indexing is triggered.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     SearchRequested::class => 'onSearch',
 * ];
 *
 * public function onSearch(SearchRequested $event): void
 * {
 *     $event->searchable(Product::class);
 *     $event->searchable(Article::class);
 * }
 * ```
 */
class SearchRequested extends LifecycleEvent
{
    /** @var array<int, string> Collected searchable model class names */
    protected array $searchableRequests = [];

    /**
     * Register a searchable model.
     *
     * @param  string  $model  Fully qualified model class name
     */
    public function searchable(string $model): void
    {
        $this->searchableRequests[] = $model;
    }

    /**
     * Get all registered searchable model class names.
     *
     * @return array<int, string>
     *
     * @internal Used by search system
     */
    public function searchableRequests(): array
    {
        return $this->searchableRequests;
    }
}
