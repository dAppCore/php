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
 * Fired when resolving a domain to a website provider.
 *
 * This event enables multi-tenancy by domain, allowing different modules to
 * handle requests based on the incoming hostname. Website modules listen
 * to this event and register themselves if their domain pattern matches.
 *
 * ## When This Event Fires
 *
 * Fired early in the request lifecycle when the framework needs to determine
 * which website provider should handle the current request. Only the first
 * provider to register wins.
 *
 * ## Domain Pattern Matching
 *
 * Use regex patterns to match domains:
 * - `/^example\.com$/` - Exact domain match
 * - `/\.example\.com$/` - Subdomain wildcard
 * - `/^(www\.)?example\.com$/` - Optional www prefix
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     DomainResolving::class => 'onDomain',
 * ];
 *
 * public function onDomain(DomainResolving $event): void
 * {
 *     if ($event->matches('/^(www\.)?mysite\.com$/')) {
 *         $event->register(MySiteProvider::class);
 *     }
 * }
 * ```
 */
class DomainResolving
{
    /**
     * The matched provider class, if any.
     */
    protected ?string $matchedProvider = null;

    /**
     * Create a new DomainResolving event.
     *
     * @param  string  $host  The incoming request hostname
     */
    public function __construct(
        public readonly string $host
    ) {}

    /**
     * Check if the incoming host matches a regex pattern.
     *
     * The host is normalized to lowercase before matching.
     *
     * @param  string  $pattern  Regex pattern to match against (e.g., '/^example\.com$/')
     * @return bool True if the pattern matches the host
     */
    public function matches(string $pattern): bool
    {
        $normalised = strtolower(parse_url('http://'.$this->host, PHP_URL_HOST) ?? $this->host);

        return (bool) preg_match($pattern, $normalised);
    }

    /**
     * Register as the matching provider for this domain.
     *
     * Only the first provider to register wins. Subsequent registrations
     * are ignored.
     *
     * @param  string  $providerClass  Fully qualified provider class name
     */
    public function register(string $providerClass): void
    {
        $this->matchedProvider = $providerClass;
    }

    /**
     * Get the matched provider class name.
     *
     * @return string|null Provider class name, or null if no match
     */
    public function matchedProvider(): ?string
    {
        return $this->matchedProvider;
    }
}
