<?php

declare(strict_types=1);

namespace Core\Website;

/**
 * Utility for working with website domain patterns.
 *
 * Mod Boot classes declare their domains via static $domains:
 *
 *     public static array $domains = [
 *         '/^example\.(com|test)$/',
 *     ];
 *
 * Domain resolution is handled by the DomainResolving event.
 * This class provides utilities for extracting domain info.
 */
class DomainResolver
{
    /**
     * Extract the $domains array from a website provider class.
     *
     * @return array<string> Domain regex patterns
     */
    public function extractDomains(string $providerClass): array
    {
        try {
            $reflection = new \ReflectionClass($providerClass);

            if (! $reflection->hasProperty('domains')) {
                return [];
            }

            $prop = $reflection->getProperty('domains');

            if (! $prop->isStatic() || ! $prop->isPublic()) {
                return [];
            }

            $domains = $prop->getValue();

            return is_array($domains) ? $domains : [];
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Get concrete domain strings for a provider.
     *
     * Converts regex patterns to actual domain names.
     * In local environment, only returns local domains (.test, localhost).
     *
     * @return array<string>
     */
    public function domainsFor(string $providerClass): array
    {
        $patterns = $this->extractDomains($providerClass);
        $domains = [];

        foreach ($patterns as $pattern) {
            $domains = array_merge($domains, $this->patternToDomains($pattern));
        }

        // In local environment, only use local domains
        if (app()->environment('local')) {
            $domains = array_filter($domains, function ($domain) {
                return str_ends_with($domain, '.test')
                    || str_ends_with($domain, '.localhost')
                    || $domain === 'localhost';
            });
        }

        return array_unique(array_values($domains));
    }

    /**
     * Convert a regex pattern to concrete domain strings.
     */
    protected function patternToDomains(string $pattern): array
    {
        $inner = trim($pattern, '/^$');

        // Simple case: fixed domain
        if (preg_match('/^[a-z0-9.-]+$/', str_replace('\\', '', $inner))) {
            return [str_replace('\\', '', $inner)];
        }

        // Pattern with optional www prefix
        if (preg_match('/^\(www\\\.\)\?(.+)$/', $inner, $match)) {
            return $this->expandTldPattern($match[1]);
        }

        return $this->expandTldPattern($inner);
    }

    /**
     * Expand TLD alternatives in a pattern.
     */
    protected function expandTldPattern(string $pattern): array
    {
        if (preg_match('/^(.+)\\\.\(([^)]+)\)$/', $pattern, $match)) {
            $base = str_replace('\\', '', $match[1]);
            $tlds = explode('|', str_replace('\\', '', $match[2]));

            return array_map(fn ($tld) => $base.'.'.$tld, $tlds);
        }

        return [str_replace('\\', '', $pattern)];
    }
}
