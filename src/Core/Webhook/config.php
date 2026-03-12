<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Secrets
    |--------------------------------------------------------------------------
    |
    | Per-source signing secrets for signature verification.
    | Modules register WebhookVerifier implementations per source.
    |
    */
    'secrets' => [
        'altum-biolinks' => env('ALTUM_BIOLINKS_WEBHOOK_SECRET'),
        'altum-analytics' => env('ALTUM_ANALYTICS_WEBHOOK_SECRET'),
        'altum-pusher' => env('ALTUM_PUSHER_WEBHOOK_SECRET'),
        'altum-socialproof' => env('ALTUM_SOCIALPROOF_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron Triggers
    |--------------------------------------------------------------------------
    |
    | Outbound HTTP triggers that replace Docker cron containers.
    | The CronTrigger action hits these endpoints every minute.
    |
    */
    'cron_triggers' => [
        'altum-biolinks' => [
            'base_url' => env('ALTUM_BIOLINKS_URL'),
            'key' => env('ALTUM_BIOLINKS_CRON_KEY'),
            'endpoints' => ['/cron', '/cron/email_reports', '/cron/broadcasts', '/cron/push_notifications'],
            'stagger_seconds' => 15,
            'offset_seconds' => 5,
        ],
        'altum-analytics' => [
            'base_url' => env('ALTUM_ANALYTICS_URL'),
            'key' => env('ALTUM_ANALYTICS_CRON_KEY'),
            'endpoints' => ['/cron', '/cron/email_reports', '/cron/broadcasts', '/cron/push_notifications'],
            'stagger_seconds' => 15,
            'offset_seconds' => 0,
        ],
        'altum-pusher' => [
            'base_url' => env('ALTUM_PUSHER_URL'),
            'key' => env('ALTUM_PUSHER_CRON_KEY'),
            'endpoints' => [
                '/cron/reset', '/cron/broadcasts', '/cron/campaigns',
                '/cron/flows', '/cron/flows_notifications', '/cron/personal_notifications',
                '/cron/rss_automations', '/cron/recurring_campaigns', '/cron/push_notifications',
            ],
            'stagger_seconds' => 7,
            'offset_seconds' => 7,
        ],
        'altum-socialproof' => [
            'base_url' => env('ALTUM_SOCIALPROOF_URL'),
            'key' => env('ALTUM_SOCIALPROOF_CRON_KEY'),
            'endpoints' => ['/cron', '/cron/email_reports', '/cron/broadcasts', '/cron/push_notifications'],
            'stagger_seconds' => 15,
            'offset_seconds' => 10,
        ],
    ],
];
