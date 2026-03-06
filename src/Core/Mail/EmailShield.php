<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Mail;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Email Shield Service
 *
 * Validates email addresses and blocks disposable email domains.
 * Tracks validation statistics for monitoring.
 *
 * ## Features
 *
 * - Format validation and disposable domain blocking
 * - MX record validation (with caching)
 * - Async validation for non-blocking deep checks
 * - Email normalization (Gmail dots, plus addressing)
 *
 * ## Disposable Domain Sources
 *
 * The disposable domain list is sourced from the community-maintained
 * [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
 * repository, which aggregates domains from multiple sources:
 *
 * - **Primary source**: `disposable_email_blocklist.conf` containing 100,000+ domains
 * - **Community contributions**: PRs from developers worldwide identifying new providers
 * - **Automated updates**: Bot-generated updates from monitoring services
 *
 * The list is stored locally at `storage/app/email-shield/disposable-domains.txt`
 * and cached for 24 hours. Use `updateDisposableDomainsList()` to fetch the
 * latest version from the upstream repository.
 *
 * ### Updating the List
 *
 * ```php
 * $shield = app(EmailShield::class);
 *
 * // Update from default source (GitHub)
 * $shield->updateDisposableDomainsList();
 *
 * // Or use a custom source
 * $shield->updateDisposableDomainsList('https://my.company.com/disposable-domains.txt');
 *
 * // Check when last updated
 * $lastUpdated = $shield->getDisposableDomainsLastUpdated();
 * ```
 *
 * ### Validation Safety
 *
 * The update process includes validation to prevent corrupted lists:
 * - Minimum 100 domains required (prevents empty/truncated lists)
 * - Comment lines (starting with #) are ignored
 * - Empty lines are filtered out
 *
 * ## MX Record Caching
 *
 * MX lookups are cached for 1 hour to reduce DNS queries and improve
 * response times. Clear the cache for a specific domain using:
 *
 * ```php
 * $shield->clearMxCache('example.com');
 * ```
 *
 * ## Statistics Tracking
 *
 * Validation statistics are recorded in the `email_shield_stats` table:
 * - Valid email count per day
 * - Invalid email count per day
 * - Disposable email count per day
 *
 * Use `getStats($from, $to)` to query statistics for a date range.
 *
 *
 * @see EmailShieldStat For statistics tracking
 * @see EmailValidationResult For validation result objects
 */
class EmailShield
{
    /**
     * Cache key for disposable domains list.
     */
    protected const CACHE_KEY = 'email_shield:disposable_domains';

    /**
     * Cache key prefix for MX record lookups.
     */
    protected const MX_CACHE_KEY_PREFIX = 'email_shield:mx:';

    /**
     * Cache key prefix for async validation results.
     */
    protected const ASYNC_RESULT_KEY_PREFIX = 'email_shield:async_result:';

    /**
     * Cache key prefix for full validation results.
     */
    protected const VALIDATION_CACHE_KEY_PREFIX = 'email_shield:validation:';

    /**
     * Cache duration in seconds (24 hours).
     */
    protected const CACHE_DURATION = 86400;

    /**
     * Cache duration for MX lookups in seconds (1 hour).
     */
    protected const MX_CACHE_DURATION = 3600;

    /**
     * Cache duration for async validation results in seconds (1 hour).
     */
    protected const ASYNC_RESULT_DURATION = 3600;

    /**
     * Cache duration for validation results in seconds (5 minutes).
     *
     * This is shorter than MX cache because validation results may change
     * (e.g., disposable domain list updates).
     */
    protected const VALIDATION_CACHE_DURATION = 300;

    /**
     * URL for disposable domains list updates.
     */
    protected const DISPOSABLE_DOMAINS_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

    /**
     * Path to disposable domains list file.
     */
    protected const DOMAINS_FILE = 'email-shield/disposable-domains.txt';

    /**
     * Gmail-like domains that use dot-stripping normalization.
     *
     * @var array<string>
     */
    protected const GMAIL_LIKE_DOMAINS = [
        'gmail.com',
        'googlemail.com',
    ];

    /**
     * Set of disposable domains (loaded from cache or file).
     *
     * @var array<string, bool>
     */
    protected array $disposableDomains = [];

    /**
     * Whether to normalize emails by default.
     */
    protected bool $normalizeByDefault = false;

    /**
     * Whether to cache validation results.
     */
    protected bool $cacheValidation = true;

    /**
     * Create a new EmailShield instance.
     */
    public function __construct()
    {
        $this->loadDisposableDomains();
    }

    /**
     * Validate an email address.
     *
     * Results are cached for 5 minutes by default to avoid repeated
     * disposable domain checks and DNS lookups for the same email.
     *
     * @param  string  $email  The email address to validate
     * @param  bool|null  $useCache  Whether to use cached results (null = use instance default)
     */
    public function validate(string $email, ?bool $useCache = null): EmailValidationResult
    {
        $useCache = $useCache ?? $this->cacheValidation;
        $normalizedEmail = strtolower(trim($email));

        // Try to get cached result
        if ($useCache) {
            $cached = $this->getCachedValidation($normalizedEmail);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Perform validation
        $result = $this->performValidation($normalizedEmail);

        // Cache the result
        if ($useCache) {
            $this->cacheValidation($normalizedEmail, $result);
        }

        return $result;
    }

    /**
     * Perform the actual validation without caching.
     *
     * @param  string  $email  The email address to validate (should be lowercase/trimmed)
     */
    protected function performValidation(string $email): EmailValidationResult
    {
        // Basic format validation
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->recordStats(isValid: false, isDisposable: false);

            return EmailValidationResult::invalid('Invalid email format');
        }

        // Extract domain
        $domain = $this->extractDomain($email);
        if (! $domain) {
            $this->recordStats(isValid: false, isDisposable: false);

            return EmailValidationResult::invalid('Could not extract domain from email');
        }

        // Check if disposable
        if ($this->isDisposable($domain)) {
            $this->recordStats(isValid: false, isDisposable: true);

            return EmailValidationResult::disposable($domain);
        }

        // Email is valid
        $this->recordStats(isValid: true, isDisposable: false);

        return EmailValidationResult::valid($domain);
    }

    /**
     * Check if a domain is disposable.
     *
     * @param  string  $domain  The domain to check
     */
    public function isDisposable(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        return isset($this->disposableDomains[$domain]);
    }

    /**
     * Record validation statistics.
     *
     * @param  bool  $isValid  Whether the email was valid
     * @param  bool  $isDisposable  Whether the email was disposable
     */
    public function recordStats(bool $isValid, bool $isDisposable = false): void
    {
        if ($isDisposable) {
            EmailShieldStat::incrementDisposable();
        } elseif ($isValid) {
            EmailShieldStat::incrementValid();
        } else {
            EmailShieldStat::incrementInvalid();
        }
    }

    /**
     * Get validation statistics for a date range.
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return array{total_valid: int, total_invalid: int, total_disposable: int, total_checked: int}
     */
    public function getStats(Carbon $from, Carbon $to): array
    {
        return EmailShieldStat::getStatsForRange($from, $to);
    }

    /**
     * Extract domain from email address.
     */
    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return null;
        }

        return strtolower(trim($parts[1]));
    }

    /**
     * Load disposable domains from cache or file.
     */
    protected function loadDisposableDomains(): void
    {
        $this->disposableDomains = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_DURATION,
            function () {
                return $this->loadDisposableDomainsFromFile();
            }
        );
    }

    /**
     * Load disposable domains from file.
     *
     * @return array<string, bool>
     */
    protected function loadDisposableDomainsFromFile(): array
    {
        $filePath = storage_path('app/'.self::DOMAINS_FILE);

        if (! File::exists($filePath)) {
            return [];
        }

        $content = File::get($filePath);
        $lines = explode("\n", $content);

        $domains = [];
        foreach ($lines as $line) {
            $domain = strtolower(trim($line));

            // Skip empty lines and comments
            if ($domain === '' || str_starts_with($domain, '#')) {
                continue;
            }

            $domains[$domain] = true;
        }

        return $domains;
    }

    /**
     * Refresh the disposable domains cache.
     */
    public function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loadDisposableDomains();
    }

    /**
     * Get the count of loaded disposable domains.
     */
    public function getDisposableDomainsCount(): int
    {
        return count($this->disposableDomains);
    }

    // =========================================================================
    // VALIDATION CACHING
    // =========================================================================

    /**
     * Enable or disable validation result caching.
     *
     * @param  bool  $enabled  Whether to cache validation results
     */
    public function withValidationCaching(bool $enabled = true): static
    {
        $this->cacheValidation = $enabled;

        return $this;
    }

    /**
     * Get cached validation result for an email.
     *
     * @param  string  $email  The email to lookup (should be normalized)
     */
    protected function getCachedValidation(string $email): ?EmailValidationResult
    {
        $cacheKey = self::VALIDATION_CACHE_KEY_PREFIX.md5($email);
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            return null;
        }

        return EmailValidationResult::fromArray($cached);
    }

    /**
     * Cache a validation result.
     *
     * @param  string  $email  The email (should be normalized)
     * @param  EmailValidationResult  $result  The validation result
     */
    protected function cacheValidation(string $email, EmailValidationResult $result): void
    {
        $cacheKey = self::VALIDATION_CACHE_KEY_PREFIX.md5($email);

        Cache::put($cacheKey, $result->toArray(), self::VALIDATION_CACHE_DURATION);
    }

    /**
     * Clear cached validation result for a specific email.
     *
     * @param  string  $email  The email to clear
     */
    public function clearValidationCache(string $email): void
    {
        $normalizedEmail = strtolower(trim($email));
        $cacheKey = self::VALIDATION_CACHE_KEY_PREFIX.md5($normalizedEmail);
        Cache::forget($cacheKey);
    }

    /**
     * Clear all validation caches (useful after disposable domain list updates).
     *
     * Note: This only clears the validation results cache, not the MX cache.
     * Call refreshCache() to also refresh the disposable domains list.
     */
    public function clearAllValidationCaches(): void
    {
        // Clear the disposable domains cache
        Cache::forget(self::CACHE_KEY);

        // Note: We cannot easily clear all individual validation caches
        // without tracking them. Consider using cache tags if needed.
        // For now, refreshing disposable domains is the main use case.

        $this->loadDisposableDomains();
    }

    /**
     * Check if validation result caching is enabled.
     */
    public function isValidationCachingEnabled(): bool
    {
        return $this->cacheValidation;
    }

    /**
     * Get the validation cache duration in seconds.
     */
    public function getValidationCacheDuration(): int
    {
        return self::VALIDATION_CACHE_DURATION;
    }

    /**
     * Check if a domain has valid MX records (with caching).
     *
     * @param  string  $domain  The domain to check
     */
    public function hasMxRecords(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $cacheKey = self::MX_CACHE_KEY_PREFIX.$domain;

        return Cache::remember(
            $cacheKey,
            self::MX_CACHE_DURATION,
            function () use ($domain): bool {
                return $this->performMxLookup($domain);
            }
        );
    }

    /**
     * Perform actual MX record lookup.
     */
    protected function performMxLookup(string $domain): bool
    {
        $mxRecords = [];

        // Suppress warnings as getmxrr returns false on failure
        $result = @getmxrr($domain, $mxRecords);

        return $result && count($mxRecords) > 0;
    }

    /**
     * Clear the MX cache for a specific domain.
     */
    public function clearMxCache(string $domain): void
    {
        $domain = strtolower(trim($domain));
        Cache::forget(self::MX_CACHE_KEY_PREFIX.$domain);
    }

    /**
     * Update the disposable domains list from remote source.
     *
     * Downloads the latest list from the configured URL and updates
     * both the local file and cache.
     *
     * @param  string|null  $url  Optional custom URL for the domains list
     * @return bool True if update was successful
     */
    public function updateDisposableDomainsList(?string $url = null): bool
    {
        $url = $url ?? self::DISPOSABLE_DOMAINS_URL;

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                Log::warning('EmailShield: Failed to fetch disposable domains list', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $content = $response->body();
            $lines = explode("\n", $content);

            // Validate we got a reasonable list
            $validDomains = array_filter($lines, function ($line) {
                $domain = strtolower(trim($line));

                return $domain !== '' && ! str_starts_with($domain, '#');
            });

            if (count($validDomains) < 100) {
                Log::warning('EmailShield: Disposable domains list seems too small', [
                    'count' => count($validDomains),
                ]);

                return false;
            }

            // Save to file
            $filePath = storage_path('app/'.self::DOMAINS_FILE);
            $directory = dirname($filePath);

            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($filePath, $content);

            // Refresh cache
            $this->refreshCache();

            Log::info('EmailShield: Updated disposable domains list', [
                'count' => count($validDomains),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('EmailShield: Exception updating disposable domains list', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the timestamp when the disposable domains file was last modified.
     */
    public function getDisposableDomainsLastUpdated(): ?Carbon
    {
        $filePath = storage_path('app/'.self::DOMAINS_FILE);

        if (! File::exists($filePath)) {
            return null;
        }

        return Carbon::createFromTimestamp(File::lastModified($filePath));
    }

    // =========================================================================
    // ASYNC VALIDATION
    // =========================================================================

    /**
     * Validate email asynchronously.
     *
     * Returns immediate result for format check and disposable domain check.
     * Queues deeper validation (MX records) for background processing.
     *
     * @param  string  $email  The email address to validate
     * @param  string|null  $queue  Queue name for async validation (null = default queue)
     * @param  callable|null  $onComplete  Optional callback when async validation completes
     * @return EmailValidationResult Immediate validation result (format + disposable check)
     */
    public function validateAsync(string $email, ?string $queue = null, ?callable $onComplete = null): EmailValidationResult
    {
        // Perform synchronous validation (format + disposable check)
        $immediateResult = $this->validate($email);

        // If immediate validation failed, no need for async checks
        if ($immediateResult->fails()) {
            return $immediateResult;
        }

        // Queue deeper validation (MX records, etc.)
        $domain = $this->extractDomain($email);
        if ($domain) {
            $this->queueDeepValidation($email, $domain, $queue, $onComplete);
        }

        // Return immediate result - caller can check async result later
        return $immediateResult;
    }

    /**
     * Queue deep validation checks for background processing.
     *
     * @param  string  $email  The email to validate
     * @param  string  $domain  The domain to check
     * @param  string|null  $queue  Queue name
     * @param  callable|null  $onComplete  Callback when complete
     */
    protected function queueDeepValidation(string $email, string $domain, ?string $queue = null, ?callable $onComplete = null): void
    {
        $job = function () use ($email, $domain, $onComplete) {
            $hasMx = $this->hasMxRecords($domain);
            $result = $hasMx
                ? EmailValidationResult::valid($domain)
                : EmailValidationResult::invalid('Domain has no valid MX records', $domain);

            // Store result in cache for retrieval
            $cacheKey = self::ASYNC_RESULT_KEY_PREFIX.md5($email);
            Cache::put($cacheKey, [
                'is_valid' => $result->isValid,
                'is_disposable' => $result->isDisposable,
                'domain' => $result->domain,
                'reason' => $result->reason,
                'has_mx' => $hasMx,
                'validated_at' => now()->toIso8601String(),
            ], self::ASYNC_RESULT_DURATION);

            // Call optional completion callback
            if ($onComplete !== null) {
                $onComplete($result, $email);
            }

            return $result;
        };

        // Dispatch to queue
        $queueName = $queue ?? config('core.email_shield.queue', 'default');

        // Use Laravel's Queue facade to dispatch closure
        Queue::push($job, [], $queueName);
    }

    /**
     * Get the async validation result for an email (if available).
     *
     * @param  string  $email  The email to check
     * @return array|null Cached validation result or null if not yet validated
     */
    public function getAsyncResult(string $email): ?array
    {
        $cacheKey = self::ASYNC_RESULT_KEY_PREFIX.md5($email);

        return Cache::get($cacheKey);
    }

    /**
     * Check if async validation has completed for an email.
     *
     * @param  string  $email  The email to check
     */
    public function hasAsyncResult(string $email): bool
    {
        return $this->getAsyncResult($email) !== null;
    }

    /**
     * Clear async validation result for an email.
     *
     * @param  string  $email  The email to clear
     */
    public function clearAsyncResult(string $email): void
    {
        $cacheKey = self::ASYNC_RESULT_KEY_PREFIX.md5($email);
        Cache::forget($cacheKey);
    }

    // =========================================================================
    // EMAIL NORMALIZATION
    // =========================================================================

    /**
     * Normalize an email address.
     *
     * Handles:
     * - Gmail dot-stripping (j.o.h.n@gmail.com = john@gmail.com)
     * - Plus addressing removal (john+spam@example.com = john@example.com)
     * - Case normalization (JOHN@EXAMPLE.COM = john@example.com)
     *
     * @param  string  $email  The email to normalize
     * @param  array  $options  Normalization options:
     *                          - 'remove_dots' => bool (default: true for Gmail)
     *                          - 'remove_plus' => bool (default: true)
     *                          - 'lowercase' => bool (default: true)
     *                          - 'gmail_domains' => array (domains to treat as Gmail-like)
     * @return string|null Normalized email or null if invalid
     */
    public function normalize(string $email, array $options = []): ?string
    {
        // Validate format first
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        [$localPart, $domain] = $parts;

        // Options with defaults
        $removeDots = $options['remove_dots'] ?? null; // null = auto-detect based on domain
        $removePlus = $options['remove_plus'] ?? true;
        $lowercase = $options['lowercase'] ?? true;
        $gmailDomains = $options['gmail_domains'] ?? self::GMAIL_LIKE_DOMAINS;

        // Lowercase domain (always - domains are case-insensitive)
        $domain = strtolower($domain);

        // Check if this is a Gmail-like domain
        $isGmailLike = in_array($domain, $gmailDomains, true);

        // Lowercase local part (optional - local parts can be case-sensitive,
        // but most providers treat them as case-insensitive)
        if ($lowercase) {
            $localPart = strtolower($localPart);
        }

        // Remove plus addressing (john+spam -> john)
        if ($removePlus) {
            $plusPos = strpos($localPart, '+');
            if ($plusPos !== false) {
                $localPart = substr($localPart, 0, $plusPos);
            }
        }

        // Remove dots for Gmail-like domains (j.o.h.n -> john)
        // Only if explicitly enabled OR domain is Gmail-like and not explicitly disabled
        $shouldRemoveDots = $removeDots === true || ($removeDots === null && $isGmailLike);
        if ($shouldRemoveDots) {
            $localPart = str_replace('.', '', $localPart);
        }

        // Handle googlemail.com -> gmail.com normalization
        if ($domain === 'googlemail.com') {
            $domain = 'gmail.com';
        }

        return $localPart.'@'.$domain;
    }

    /**
     * Get the canonical form of an email for deduplication.
     *
     * This is a strict normalization that should produce the same
     * result for all variations of the same mailbox.
     *
     * @param  string  $email  The email to canonicalize
     * @return string|null Canonical email or null if invalid
     */
    public function canonicalize(string $email): ?string
    {
        return $this->normalize($email, [
            'remove_dots' => null, // Auto-detect based on domain
            'remove_plus' => true,
            'lowercase' => true,
        ]);
    }

    /**
     * Check if two emails refer to the same mailbox.
     *
     * @param  string  $email1  First email
     * @param  string  $email2  Second email
     * @return bool True if emails refer to the same mailbox
     */
    public function isSameMailbox(string $email1, string $email2): bool
    {
        $canonical1 = $this->canonicalize($email1);
        $canonical2 = $this->canonicalize($email2);

        if ($canonical1 === null || $canonical2 === null) {
            return false;
        }

        return $canonical1 === $canonical2;
    }

    /**
     * Extract local part from email address.
     *
     * @param  string  $email  The email address
     * @return string|null Local part or null if invalid
     */
    public function extractLocalPart(string $email): ?string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return null;
        }

        return $parts[0];
    }

    /**
     * Check if an email uses plus addressing.
     *
     * @param  string  $email  The email to check
     */
    public function hasPlusAddressing(string $email): bool
    {
        $localPart = $this->extractLocalPart($email);

        return $localPart !== null && str_contains($localPart, '+');
    }

    /**
     * Get the base email without plus addressing.
     *
     * @param  string  $email  The email to strip
     * @return string|null Base email or null if invalid
     */
    public function stripPlusAddressing(string $email): ?string
    {
        return $this->normalize($email, [
            'remove_dots' => false,
            'remove_plus' => true,
            'lowercase' => false,
        ]);
    }

    /**
     * Check if email is from a Gmail-like domain.
     *
     * @param  string  $email  The email to check
     */
    public function isGmailLike(string $email): bool
    {
        $domain = $this->extractDomain($email);

        return $domain !== null && in_array($domain, self::GMAIL_LIKE_DOMAINS, true);
    }
}
