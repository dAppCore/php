<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Validation;

use Core\Seo\SeoMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects canonical URL conflicts across SEO metadata.
 *
 * Canonical URLs are used to indicate the preferred version of a page to search
 * engines. Having multiple pages with the same canonical URL (unless intentional
 * for duplicate content consolidation) can indicate configuration errors.
 *
 * This validator:
 * - Detects duplicate canonical URLs across different pages
 * - Identifies self-referencing canonical URL issues
 * - Finds missing canonical URLs
 * - Detects protocol/www inconsistencies
 *
 * Usage:
 *   $validator = new CanonicalUrlValidator();
 *   $conflicts = $validator->findConflicts();
 *   $issues = $validator->audit();
 */
class CanonicalUrlValidator
{
    /**
     * Find canonical URL conflicts (duplicates).
     *
     * Returns groups of SEO metadata records that share the same canonical URL.
     *
     * @param  int  $minCount  Minimum count to consider a conflict (default 2)
     * @return Collection<string, Collection<int, SeoMetadata>>
     */
    public function findConflicts(int $minCount = 2): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        // Find canonical URLs that appear more than once
        $duplicates = DB::table('seo_metadata')
            ->select('canonical_url', DB::raw('COUNT(*) as count'))
            ->whereNotNull('canonical_url')
            ->where('canonical_url', '!=', '')
            ->groupBy('canonical_url')
            ->having('count', '>=', $minCount)
            ->pluck('canonical_url');

        if ($duplicates->isEmpty()) {
            return collect();
        }

        // Get the full records for each duplicate canonical URL
        return SeoMetadata::whereIn('canonical_url', $duplicates)
            ->get()
            ->groupBy('canonical_url');
    }

    /**
     * Check if a specific canonical URL has conflicts.
     *
     * @return array{has_conflict: bool, count: int, records: Collection<int, SeoMetadata>}
     */
    public function checkUrl(string $canonicalUrl): array
    {
        if (! $this->tableExists()) {
            return [
                'has_conflict' => false,
                'count' => 0,
                'records' => collect(),
            ];
        }

        $records = SeoMetadata::where('canonical_url', $canonicalUrl)->get();

        return [
            'has_conflict' => $records->count() > 1,
            'count' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Check if adding a canonical URL would create a conflict.
     *
     * @param  string  $canonicalUrl  The URL to check
     * @param  int|null  $excludeId  ID to exclude (for updates)
     */
    public function wouldConflict(string $canonicalUrl, ?int $excludeId = null): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $query = SeoMetadata::where('canonical_url', $canonicalUrl);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Perform a full audit of canonical URLs.
     *
     * @return array{
     *     duplicates: Collection<string, Collection<int, SeoMetadata>>,
     *     missing: Collection<int, SeoMetadata>,
     *     protocol_issues: Collection<int, SeoMetadata>,
     *     www_inconsistencies: Collection<int, SeoMetadata>,
     *     self_referencing: Collection<int, SeoMetadata>,
     *     summary: array{total: int, with_canonical: int, without_canonical: int, duplicate_count: int, issue_count: int}
     * }
     */
    public function audit(): array
    {
        if (! $this->tableExists()) {
            return $this->emptyAuditResult();
        }

        $allRecords = SeoMetadata::all();
        $total = $allRecords->count();

        // Find records without canonical URLs
        $missing = $allRecords->filter(fn ($r) => empty($r->canonical_url));

        // Find records with canonical URLs
        $withCanonical = $allRecords->filter(fn ($r) => ! empty($r->canonical_url));

        // Find duplicates
        $duplicates = $this->findConflicts();
        $duplicateCount = $duplicates->sum(fn ($group) => $group->count());

        // Find protocol issues (HTTP vs HTTPS)
        $protocolIssues = $withCanonical->filter(function ($record) {
            return str_starts_with($record->canonical_url, 'http://');
        });

        // Find www inconsistencies
        $wwwInconsistencies = $this->findWwwInconsistencies($withCanonical);

        // Find self-referencing issues (canonical pointing to itself is valid,
        // but canonical pointing to different URL on same resource may indicate issues)
        $selfReferencing = $this->findSelfReferencingIssues($withCanonical);

        $issueCount = $duplicateCount + $protocolIssues->count() +
                      $wwwInconsistencies->count() + $selfReferencing->count();

        return [
            'duplicates' => $duplicates,
            'missing' => $missing,
            'protocol_issues' => $protocolIssues,
            'www_inconsistencies' => $wwwInconsistencies,
            'self_referencing' => $selfReferencing,
            'summary' => [
                'total' => $total,
                'with_canonical' => $withCanonical->count(),
                'without_canonical' => $missing->count(),
                'duplicate_count' => $duplicateCount,
                'issue_count' => $issueCount,
            ],
        ];
    }

    /**
     * Validate a canonical URL format.
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateUrl(string $canonicalUrl): array
    {
        $errors = [];
        $warnings = [];

        // Check if it's a valid URL
        if (! filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';

            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Parse URL components
        $parts = parse_url($canonicalUrl);

        // Check protocol
        if (($parts['scheme'] ?? '') === 'http') {
            $warnings[] = 'Canonical URL should use HTTPS';
        }

        // Check for query strings (usually not recommended)
        if (! empty($parts['query'])) {
            $warnings[] = 'Canonical URLs generally should not include query strings';
        }

        // Check for fragments (not allowed in canonical URLs)
        if (! empty($parts['fragment'])) {
            $errors[] = 'Canonical URLs must not include URL fragments (#)';
        }

        // Check for trailing slash consistency
        $path = $parts['path'] ?? '/';
        if ($path !== '/' && ! str_ends_with($path, '/') && ! pathinfo($path, PATHINFO_EXTENSION)) {
            $warnings[] = 'Consider using consistent trailing slash policy';
        }

        // Check for double slashes in path
        if (str_contains($path, '//')) {
            $errors[] = 'URL path contains double slashes';
        }

        // Check for uppercase in URL
        if ($canonicalUrl !== strtolower($canonicalUrl)) {
            $warnings[] = 'Canonical URLs should be lowercase for consistency';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Normalise a canonical URL for comparison.
     */
    public function normalizeUrl(string $url): string
    {
        // Parse URL
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        // Normalize to lowercase
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';

        // Remove default ports
        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        // Remove trailing slash from path (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Rebuild URL
        $normalized = $scheme.'://'.$host;

        if ($port !== null) {
            $normalized .= ':'.$port;
        }

        $normalized .= $path;

        return $normalized;
    }

    /**
     * Find www/non-www inconsistencies in canonical URLs.
     *
     * @param  Collection<int, SeoMetadata>  $records
     * @return Collection<int, SeoMetadata>
     */
    protected function findWwwInconsistencies(Collection $records): Collection
    {
        // Group by normalized domain (without www)
        $byDomain = $records->groupBy(function ($record) {
            $host = parse_url($record->canonical_url, PHP_URL_HOST);
            if ($host === null || $host === false) {
                return null;
            }

            // Remove www prefix for grouping
            return preg_replace('/^www\./', '', $host);
        })->filter(fn ($group, $key) => $key !== null);

        // Find groups with both www and non-www versions
        $inconsistent = collect();

        foreach ($byDomain as $domain => $group) {
            $hasWww = $group->some(fn ($r) => str_contains($r->canonical_url, '://www.'));
            $hasNonWww = $group->some(fn ($r) => ! str_contains($r->canonical_url, '://www.'));

            if ($hasWww && $hasNonWww) {
                $inconsistent = $inconsistent->merge($group);
            }
        }

        return $inconsistent;
    }

    /**
     * Find self-referencing issues.
     *
     * Self-referencing canonicals are generally fine, but we flag cases where
     * the canonical URL doesn't match the expected URL of the seoable model.
     *
     * @param  Collection<int, SeoMetadata>  $records
     * @return Collection<int, SeoMetadata>
     */
    protected function findSelfReferencingIssues(Collection $records): Collection
    {
        return $records->filter(function ($record) {
            // Load the seoable model if it has a getUrl() method
            $seoable = $record->seoable;

            if ($seoable === null) {
                return false;
            }

            // Check if the model has a URL method
            if (! method_exists($seoable, 'getUrl') && ! method_exists($seoable, 'url')) {
                return false;
            }

            $modelUrl = method_exists($seoable, 'getUrl')
                ? $seoable->getUrl()
                : $seoable->url;

            if ($modelUrl === null) {
                return false;
            }

            // Normalize both URLs for comparison
            $normalizedCanonical = $this->normalizeUrl($record->canonical_url);
            $normalizedModel = $this->normalizeUrl($modelUrl);

            // Flag if they're significantly different
            return $normalizedCanonical !== $normalizedModel;
        });
    }

    /**
     * Check if the seo_metadata table exists.
     */
    protected function tableExists(): bool
    {
        return Schema::hasTable('seo_metadata');
    }

    /**
     * Return empty audit result.
     *
     * @return array{
     *     duplicates: Collection<string, Collection<int, SeoMetadata>>,
     *     missing: Collection<int, SeoMetadata>,
     *     protocol_issues: Collection<int, SeoMetadata>,
     *     www_inconsistencies: Collection<int, SeoMetadata>,
     *     self_referencing: Collection<int, SeoMetadata>,
     *     summary: array{total: int, with_canonical: int, without_canonical: int, duplicate_count: int, issue_count: int}
     * }
     */
    protected function emptyAuditResult(): array
    {
        return [
            'duplicates' => collect(),
            'missing' => collect(),
            'protocol_issues' => collect(),
            'www_inconsistencies' => collect(),
            'self_referencing' => collect(),
            'summary' => [
                'total' => 0,
                'with_canonical' => 0,
                'without_canonical' => 0,
                'duplicate_count' => 0,
                'issue_count' => 0,
            ],
        ];
    }
}
