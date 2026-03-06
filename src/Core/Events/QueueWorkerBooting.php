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
 * Fired when a queue worker is starting up.
 *
 * Modules listen to this event to perform queue-specific initialization or
 * register job classes that need explicit registration.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireQueueWorkerBooting()` when the
 * application detects it's running in queue worker context (i.e., when
 * `queue.worker` is bound in the container).
 *
 * Not fired during web requests, API calls, or console commands.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     QueueWorkerBooting::class => 'onQueueWorker',
 * ];
 *
 * public function onQueueWorker(QueueWorkerBooting $event): void
 * {
 *     $event->job(ProcessOrderJob::class);
 *     $event->job(SendNotificationJob::class);
 * }
 * ```
 *
 * Note: Most Laravel jobs don't need explicit registration. This event
 * is primarily for queue-specific initialization or custom job handling.
 *
 *
 * @see ConsoleBooting For CLI-specific initialization
 */
class QueueWorkerBooting extends LifecycleEvent
{
    /** @var array<int, string> Collected job class names */
    protected array $jobRequests = [];

    /**
     * Register a job class.
     *
     * @param  string  $class  Fully qualified job class name
     */
    public function job(string $class): void
    {
        $this->jobRequests[] = $class;
    }

    /**
     * Get all registered job class names.
     *
     * @return array<int, string>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function jobRequests(): array
    {
        return $this->jobRequests;
    }
}
