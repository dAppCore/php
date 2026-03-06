<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Website\Host\Mail\ContactFormSubmission;

describe('Mail Configuration', function () {
    it('has mail configuration set', function () {
        expect(config('mail.default'))->not->toBeNull()
            ->and(config('mail.from.address'))->not->toBeNull()
            ->and(config('mail.from.name'))->not->toBeNull();
    });

    it('has contact recipient configured', function () {
        expect(config('mail.contact_recipient'))->not->toBeNull();
    });

    it('has mailers configured', function () {
        expect(config('mail.mailers'))->toBeArray()
            ->and(config('mail.mailers.smtp'))->toBeArray()
            ->and(config('mail.mailers.log'))->toBeArray();
    });

    it('smtp mailer has required settings when enabled', function () {
        if (config('mail.default') !== 'smtp') {
            $this->markTestSkipped('SMTP not configured as default mailer');
        }

        $smtp = config('mail.mailers.smtp');

        expect($smtp['host'])->not->toBeNull()
            ->and($smtp['port'])->not->toBeNull();
    });
});

describe('Mail Sending', function () {
    it('can send mail via log driver', function () {
        Config::set('mail.default', 'log');

        Mail::raw('Test email content', function ($message) {
            $message->to('test@example.com')
                ->subject('Test Email');
        });

        // If we get here without exception, mail sending works
        expect(true)->toBeTrue();
    });

    it('ContactFormSubmission can be queued', function () {
        Queue::fake();

        $mailable = new ContactFormSubmission(
            name: 'Test User',
            email: 'test@example.com',
            subjectType: 'general',
            messageBody: 'Test message for queue testing.'
        );

        Mail::to('recipient@example.com')->queue($mailable);

        Queue::assertPushed(\Illuminate\Mail\SendQueuedMailable::class);
    });

    it('ContactFormSubmission implements ShouldQueue', function () {
        expect(ContactFormSubmission::class)
            ->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});

describe('Mail Templates', function () {
    it('contact form submission template exists', function () {
        $view = 'pages::emails.contact-form-submission';

        expect(view()->exists($view))->toBeTrue();
    });

    it('contact form submission template renders without error', function () {
        $mailable = new ContactFormSubmission(
            name: 'Test User',
            email: 'test@example.com',
            subjectType: 'support',
            messageBody: 'I need help with my account settings.'
        );

        $rendered = $mailable->render();

        expect($rendered)->toBeString()
            ->and($rendered)->toContain('Test User')
            ->and($rendered)->toContain('test@example.com')
            ->and($rendered)->toContain('I need help with my account settings.');
    });
});

describe('Queue Configuration for Mail', function () {
    it('queue connection is configured', function () {
        expect(config('queue.default'))->not->toBeNull();
    });

    it('redis queue has correct settings when used', function () {
        if (config('queue.default') !== 'redis') {
            $this->markTestSkipped('Redis not configured as default queue');
        }

        expect(config('queue.connections.redis'))->toBeArray()
            ->and(config('queue.connections.redis.connection'))->not->toBeNull();
    });

    it('sync queue works for testing', function () {
        Config::set('queue.default', 'sync');
        Mail::fake();

        $mailable = new ContactFormSubmission(
            name: 'Sync Test',
            email: 'sync@example.com',
            subjectType: 'general',
            messageBody: 'Testing synchronous queue.'
        );

        Mail::to('recipient@example.com')->queue($mailable);

        Mail::assertQueued(ContactFormSubmission::class);
    });
});
