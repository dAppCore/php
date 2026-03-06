<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer;

use Illuminate\Support\ServiceProvider;

/**
 * Core Bouncer - Early-exit middleware for security and SEO.
 *
 * Two responsibilities:
 * 1. Block bad actors (honeypot critical hits) before wasting CPU
 * 2. Handle SEO redirects before Laravel routing
 *
 * ## Honeypot Configuration
 *
 * The honeypot system traps bots that ignore robots.txt by monitoring
 * paths listed as disallowed. Configure via `config/core.php` under
 * the `bouncer.honeypot` key:
 *
 * ### Configuration Options
 *
 * | Option | Environment Variable | Default | Description |
 * |--------|---------------------|---------|-------------|
 * | `auto_block_critical` | `CORE_BOUNCER_HONEYPOT_AUTO_BLOCK` | `true` | Auto-block IPs hitting critical paths like /admin or /.env |
 * | `rate_limit_max` | `CORE_BOUNCER_HONEYPOT_RATE_LIMIT_MAX` | `10` | Max honeypot log entries per IP within the time window |
 * | `rate_limit_window` | `CORE_BOUNCER_HONEYPOT_RATE_LIMIT_WINDOW` | `60` | Rate limit window in seconds (default: 1 minute) |
 * | `severity_levels.critical` | `CORE_BOUNCER_HONEYPOT_SEVERITY_CRITICAL` | `'critical'` | Label for critical severity hits |
 * | `severity_levels.warning` | `CORE_BOUNCER_HONEYPOT_SEVERITY_WARNING` | `'warning'` | Label for warning severity hits |
 * | `critical_paths` | N/A | See below | Paths that trigger critical severity |
 *
 * ### Default Critical Paths
 *
 * These paths indicate malicious probing and trigger 'critical' severity:
 * - `admin` - Admin panel probing
 * - `wp-admin` - WordPress admin probing
 * - `wp-login.php` - WordPress login probing
 * - `administrator` - Joomla admin probing
 * - `phpmyadmin` - Database admin probing
 * - `.env` - Environment file probing
 * - `.git` - Git repository probing
 *
 * ### Customizing Critical Paths
 *
 * Override in your `config/core.php`:
 *
 * ```php
 * 'bouncer' => [
 *     'honeypot' => [
 *         'critical_paths' => [
 *             'admin',
 *             'wp-admin',
 *             '.env',
 *             '.git',
 *             'backup',       // Add custom paths
 *             'config.php',
 *         ],
 *     ],
 * ],
 * ```
 *
 * ### Blocking Workflow
 *
 * 1. Bot hits a honeypot path (e.g., /admin)
 * 2. Path is checked against `critical_paths` (prefix matching)
 * 3. If critical and `auto_block_critical` is true, IP is blocked immediately
 * 4. Otherwise, entry is added to `honeypot_hits` with 'pending' status
 * 5. Admin reviews pending entries via `BlocklistService::getPending()`
 * 6. Admin approves or rejects via `approve($ip)` or `reject($ip)`
 *
 * ### Rate Limiting
 *
 * To prevent DoS via log flooding, honeypot logging is rate-limited:
 * - Default: 10 entries per IP per minute
 * - Exceeded entries are silently dropped
 * - Rate limit uses Laravel's RateLimiter facade
 *
 * @see BlocklistService For IP blocking functionality
 * @see BouncerMiddleware For the early-exit middleware
 */
class Boot extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlocklistService::class);
        $this->app->singleton(RedirectService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
