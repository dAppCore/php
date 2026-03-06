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
 * Fired when mail functionality is needed.
 *
 * Modules listen to this event to register mail templates, custom mailers,
 * or mail-related services. This enables lazy loading of mail dependencies
 * until email actually needs to be sent.
 *
 * ## When This Event Fires
 *
 * Fired when the mail system initializes, typically just before sending
 * the first email in a request.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     MailSending::class => 'onMail',
 * ];
 *
 * public function onMail(MailSending $event): void
 * {
 *     $event->mailable(OrderConfirmationMail::class);
 *     $event->mailable(WelcomeEmail::class);
 * }
 * ```
 */
class MailSending extends LifecycleEvent
{
    /** @var array<int, string> Collected mailable class names */
    protected array $mailableRequests = [];

    /**
     * Register a mailable class.
     *
     * @param  string  $class  Fully qualified mailable class name
     */
    public function mailable(string $class): void
    {
        $this->mailableRequests[] = $class;
    }

    /**
     * Get all registered mailable class names.
     *
     * @return array<int, string>
     *
     * @internal Used by mail system
     */
    public function mailableRequests(): array
    {
        return $this->mailableRequests;
    }
}
