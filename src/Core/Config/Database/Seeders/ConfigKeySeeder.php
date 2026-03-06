<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Database\Seeders;

use Core\Config\Enums\ConfigType;
use Core\Config\Models\ConfigKey;
use Illuminate\Database\Seeder;

/**
 * Seed known configuration keys.
 *
 * Only actual settings - no parent/group markers.
 * Hierarchy is implicit in the key names.
 */
class ConfigKeySeeder extends Seeder
{
    public function run(): void
    {
        $keys = [
            // CDN - Bunny
            ['cdn.bunny.api_key', ConfigType::STRING, 'cdn', 'Bunny API key'],
            ['cdn.bunny.pull_zone_id', ConfigType::STRING, 'cdn', 'Bunny pull zone ID'],
            ['cdn.bunny.pull_zone_url', ConfigType::STRING, 'cdn', 'Bunny pull zone URL'],
            ['cdn.bunny.push_enabled', ConfigType::BOOL, 'cdn', 'Enable pushing assets to CDN', false],

            // CDN - Bunny Storage Public
            ['cdn.bunny.storage.public.name', ConfigType::STRING, 'cdn', 'Public storage zone name'],
            ['cdn.bunny.storage.public.api_key', ConfigType::STRING, 'cdn', 'Public storage API key'],
            ['cdn.bunny.storage.public.hostname', ConfigType::STRING, 'cdn', 'Public storage hostname', 'storage.bunnycdn.com'],
            ['cdn.bunny.storage.public.region', ConfigType::STRING, 'cdn', 'Public storage region', 'de'],

            // CDN - Bunny Storage Private
            ['cdn.bunny.storage.private.name', ConfigType::STRING, 'cdn', 'Private storage zone name'],
            ['cdn.bunny.storage.private.api_key', ConfigType::STRING, 'cdn', 'Private storage API key'],
            ['cdn.bunny.storage.private.hostname', ConfigType::STRING, 'cdn', 'Private storage hostname', 'storage.bunnycdn.com'],
            ['cdn.bunny.storage.private.region', ConfigType::STRING, 'cdn', 'Private storage region', 'de'],

            // Storage - Hetzner S3
            ['storage.hetzner.key', ConfigType::STRING, 'storage', 'Hetzner S3 access key'],
            ['storage.hetzner.secret', ConfigType::STRING, 'storage', 'Hetzner S3 secret key'],
            ['storage.hetzner.region', ConfigType::STRING, 'storage', 'Hetzner S3 region', 'eu-central'],
            ['storage.hetzner.bucket', ConfigType::STRING, 'storage', 'Hetzner S3 bucket name'],
            ['storage.hetzner.endpoint', ConfigType::STRING, 'storage', 'Hetzner S3 endpoint'],

            // Social
            ['social.default_timezone', ConfigType::STRING, 'social', 'Default timezone for scheduling', 'Europe/London'],
            ['social.max_accounts', ConfigType::INT, 'social', 'Maximum connected accounts', 5],
            ['social.max_scheduled_posts', ConfigType::INT, 'social', 'Maximum scheduled posts', 100],

            // Social - AI
            ['social.ai.enabled', ConfigType::BOOL, 'social', 'Enable AI features', true],
            ['social.ai.provider', ConfigType::STRING, 'social', 'AI provider (claude, openai, gemini)', 'claude'],

            // Analytics
            ['analytics.retention_days', ConfigType::INT, 'analytics', 'Data retention in days', 365],
            ['analytics.sample_rate', ConfigType::FLOAT, 'analytics', 'Sampling rate (0.0-1.0)', 1.0],
            ['analytics.heatmaps_enabled', ConfigType::BOOL, 'analytics', 'Enable heatmap tracking', true],
            ['analytics.session_replay_enabled', ConfigType::BOOL, 'analytics', 'Enable session replay', true],

            // Bio
            ['bio.max_pages', ConfigType::INT, 'bio', 'Maximum bio pages per workspace', 5],
            ['bio.custom_domains_enabled', ConfigType::BOOL, 'bio', 'Enable custom domains', true],
            ['bio.default_theme', ConfigType::STRING, 'bio', 'Default theme slug', 'minimal'],
        ];

        foreach ($keys as $key) {
            ConfigKey::firstOrCreate(
                ['code' => $key[0]],
                [
                    'type' => $key[1],
                    'category' => $key[2],
                    'description' => $key[3] ?? null,
                    'default_value' => $key[4] ?? null,
                ]
            );
        }
    }
}
