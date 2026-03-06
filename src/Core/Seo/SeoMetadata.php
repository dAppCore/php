<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SEO metadata for any model.
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical_url
 * @property array<string, mixed>|null $og_data
 * @property array<string, mixed>|null $twitter_data
 * @property array<string, mixed>|null $schema_markup
 * @property string|null $robots
 * @property string|null $focus_keyword
 * @property int|null $seo_score
 * @property array<string>|null $seo_issues
 * @property array<string>|null $seo_suggestions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'title',
        'description',
        'canonical_url',
        'og_data',
        'twitter_data',
        'schema_markup',
        'robots',
        'focus_keyword',
        'seo_score',
        'seo_issues',
        'seo_suggestions',
    ];

    protected $casts = [
        'og_data' => 'array',
        'twitter_data' => 'array',
        'seo_issues' => 'array',
        'seo_suggestions' => 'array',
        'seo_score' => 'integer',
        // Note: schema_markup uses lazy loading via accessor - not cast here
    ];

    /**
     * Cached parsed schema markup (lazy loaded).
     *
     * @var array<string, mixed>|null
     */
    protected ?array $parsedSchemaMarkup = null;

    /**
     * Whether schema markup has been loaded.
     */
    protected bool $schemaMarkupLoaded = false;

    /**
     * Attributes that should be deferred (not loaded eagerly).
     *
     * @var array<string>
     */
    protected array $deferredAttributes = ['schema_markup'];

    /**
     * Get the schema markup with lazy loading.
     *
     * The schema_markup is parsed only when first accessed, improving
     * performance for queries that don't need the schema data.
     *
     * @return array<string, mixed>|null
     */
    public function getSchemaMarkupAttribute(): ?array
    {
        if ($this->schemaMarkupLoaded) {
            return $this->parsedSchemaMarkup;
        }

        $this->schemaMarkupLoaded = true;

        $rawValue = $this->attributes['schema_markup'] ?? null;

        if ($rawValue === null) {
            $this->parsedSchemaMarkup = null;

            return null;
        }

        // If it's already an array (from direct assignment), return it
        if (is_array($rawValue)) {
            $this->parsedSchemaMarkup = $rawValue;

            return $this->parsedSchemaMarkup;
        }

        // Parse JSON string
        $decoded = json_decode($rawValue, true);
        $this->parsedSchemaMarkup = is_array($decoded) ? $decoded : null;

        return $this->parsedSchemaMarkup;
    }

    /**
     * Set the schema markup attribute.
     *
     * @param  array<string, mixed>|string|null  $value
     */
    public function setSchemaMarkupAttribute(array|string|null $value): void
    {
        // Reset the lazy loading cache
        $this->parsedSchemaMarkup = null;
        $this->schemaMarkupLoaded = false;

        if ($value === null) {
            $this->attributes['schema_markup'] = null;

            return;
        }

        if (is_array($value)) {
            $this->attributes['schema_markup'] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return;
        }

        // Assume it's already a JSON string
        $this->attributes['schema_markup'] = $value;
    }

    /**
     * Check if schema markup is loaded without triggering lazy load.
     */
    public function isSchemaMarkupLoaded(): bool
    {
        return $this->schemaMarkupLoaded;
    }

    /**
     * Check if this model has schema markup (without fully parsing it).
     */
    public function hasSchemaMarkup(): bool
    {
        return ! empty($this->attributes['schema_markup'] ?? null);
    }

    /**
     * Get the parent seoable model.
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Generate JSON-LD script tag.
     *
     * Uses JSON_HEX_TAG to prevent XSS via </script> in content.
     */
    public function getJsonLdAttribute(): string
    {
        if (empty($this->schema_markup)) {
            return '';
        }

        return '<script type="application/ld+json">'.
            json_encode($this->schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG).
            '</script>';
    }

    /**
     * Generate all meta tags as HTML.
     */
    public function getMetaTagsAttribute(): string
    {
        $tags = [];

        if ($this->title) {
            $tags[] = '<title>'.e($this->title).'</title>';
        }

        if ($this->description) {
            $tags[] = '<meta name="description" content="'.e($this->description).'">';
        }

        if ($this->canonical_url) {
            $tags[] = '<link rel="canonical" href="'.e($this->canonical_url).'">';
        }

        if ($this->robots) {
            $tags[] = '<meta name="robots" content="'.e($this->robots).'">';
        }

        // Open Graph tags
        if (! empty($this->og_data)) {
            foreach ($this->og_data as $property => $content) {
                if ($content) {
                    $tags[] = '<meta property="og:'.$property.'" content="'.e($content).'">';
                }
            }
        }

        // Twitter Card tags
        if (! empty($this->twitter_data)) {
            foreach ($this->twitter_data as $name => $content) {
                if ($content) {
                    $tags[] = '<meta name="twitter:'.$name.'" content="'.e($content).'">';
                }
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * Get SEO score colour for UI display.
     */
    public function getScoreColorAttribute(): string
    {
        if ($this->seo_score === null) {
            return 'zinc';
        }

        return match (true) {
            $this->seo_score >= 80 => 'green',
            $this->seo_score >= 50 => 'amber',
            default => 'red',
        };
    }

    /**
     * Check if there are issues to address.
     */
    public function hasIssues(): bool
    {
        return ! empty($this->seo_issues);
    }

    /**
     * Get the count of issues.
     */
    public function getIssueCountAttribute(): int
    {
        return count($this->seo_issues ?? []);
    }

    /**
     * Validate Open Graph image dimensions.
     *
     * @param  bool  $fetchRemote  Whether to fetch remote images
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, dimensions: array{width: int|null, height: int|null}}
     */
    public function validateOgImage(bool $fetchRemote = true): array
    {
        $validator = new Validation\OgImageValidator;

        return $validator->validateOgData($this->og_data);
    }

    /**
     * Check if the OG image meets minimum requirements.
     */
    public function hasValidOgImage(): bool
    {
        return $this->validateOgImage()['valid'];
    }

    /**
     * Get OG image validation warnings.
     *
     * @return array<string>
     */
    public function getOgImageWarnings(): array
    {
        return $this->validateOgImage()['warnings'];
    }

    /**
     * Validate the canonical URL format.
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateCanonicalUrl(): array
    {
        if (empty($this->canonical_url)) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => ['No canonical URL specified'],
            ];
        }

        $validator = new Validation\CanonicalUrlValidator;

        return $validator->validateUrl($this->canonical_url);
    }

    /**
     * Check if this canonical URL conflicts with other records.
     *
     * @return array{has_conflict: bool, count: int, records: \Illuminate\Support\Collection}
     */
    public function checkCanonicalConflict(): array
    {
        if (empty($this->canonical_url)) {
            return [
                'has_conflict' => false,
                'count' => 0,
                'records' => collect(),
            ];
        }

        $validator = new Validation\CanonicalUrlValidator;
        $result = $validator->checkUrl($this->canonical_url);

        // Exclude self from conflict check
        $otherRecords = $result['records']->filter(fn ($r) => $r->id !== $this->id);

        return [
            'has_conflict' => $otherRecords->isNotEmpty(),
            'count' => $otherRecords->count(),
            'records' => $otherRecords,
        ];
    }

    /**
     * Check if the canonical URL is valid and has no conflicts.
     */
    public function hasValidCanonicalUrl(): bool
    {
        $validation = $this->validateCanonicalUrl();
        $conflict = $this->checkCanonicalConflict();

        return $validation['valid'] && ! $conflict['has_conflict'];
    }

    /**
     * Record the current score for trend tracking.
     *
     * @param  bool  $force  Force recording even if within minimum interval
     * @return Models\SeoScoreHistory|null The created record or null if skipped
     */
    public function recordScore(bool $force = false): ?Models\SeoScoreHistory
    {
        $trend = app(Analytics\SeoScoreTrend::class);

        return $trend->recordScore($this, $force);
    }

    /**
     * Get score history for this metadata.
     *
     * @param  int  $limit  Maximum records to return
     * @return \Illuminate\Support\Collection<int, Models\SeoScoreHistory>
     */
    public function getScoreHistory(int $limit = 100): \Illuminate\Support\Collection
    {
        $trend = app(Analytics\SeoScoreTrend::class);

        return $trend->getHistory($this, $limit);
    }

    /**
     * Get daily score trend for this metadata.
     *
     * @param  int  $days  Days to look back
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getDailyScoreTrend(int $days = 30): \Illuminate\Support\Collection
    {
        $trend = app(Analytics\SeoScoreTrend::class);

        return $trend->getDailyTrend($this, $days);
    }

    /**
     * Get weekly score trend for this metadata.
     *
     * @param  int  $weeks  Weeks to look back
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getWeeklyScoreTrend(int $weeks = 12): \Illuminate\Support\Collection
    {
        $trend = app(Analytics\SeoScoreTrend::class);

        return $trend->getWeeklyTrend($this, $weeks);
    }

    /**
     * Check if score has improved since last recording.
     *
     * @return bool|null True if improved, false if declined, null if no history
     */
    public function hasScoreImproved(): ?bool
    {
        $latest = Models\SeoScoreHistory::latestForModel(
            $this->seoable_type,
            $this->seoable_id
        );

        if ($latest === null) {
            return null;
        }

        return $this->seo_score > $latest->score;
    }

    /**
     * Get the score change since last recording.
     *
     * @return int|null Change amount or null if no history
     */
    public function getScoreChange(): ?int
    {
        $latest = Models\SeoScoreHistory::latestForModel(
            $this->seoable_type,
            $this->seoable_id
        );

        if ($latest === null) {
            return null;
        }

        return $this->seo_score - $latest->score;
    }

    /**
     * Validate the structured data (schema markup).
     *
     * @return array{valid: bool, errors: array, warnings: array, info: array, rich_results: array, types_found: array}
     */
    public function validateStructuredData(): array
    {
        if (! $this->hasSchemaMarkup()) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => [['code' => 'no_schema', 'message' => 'No structured data defined', 'path' => 'schema_markup', 'fix' => 'Add schema.org structured data to improve SEO.']],
                'info' => [],
                'rich_results' => [],
                'types_found' => [],
            ];
        }

        $tester = new Validation\StructuredDataTester;

        return $tester->test($this->schema_markup);
    }

    /**
     * Get a detailed structured data report.
     *
     * @return array{summary: array, types: array, rich_results: array, errors: array, warnings: array, recommendations: array, score: int}
     */
    public function getStructuredDataReport(): array
    {
        if (! $this->hasSchemaMarkup()) {
            return [
                'summary' => ['valid' => true, 'error_count' => 0, 'warning_count' => 1],
                'types' => [],
                'rich_results' => [],
                'errors' => [],
                'warnings' => [[
                    'code' => 'no_schema',
                    'message' => 'No structured data defined',
                    'path' => 'schema_markup',
                    'explanation' => 'Structured data helps search engines understand your content.',
                    'fix' => 'Add schema.org structured data to improve SEO and enable rich results.',
                ]],
                'recommendations' => ['Add schema.org structured data to enable rich results in search.'],
                'score' => 50,
            ];
        }

        $tester = new Validation\StructuredDataTester;

        return $tester->generateReport($this->schema_markup);
    }

    /**
     * Check if this page is eligible for rich results.
     *
     * @return array<string> List of eligible rich result features
     */
    public function getRichResultsEligibility(): array
    {
        if (! $this->hasSchemaMarkup()) {
            return [];
        }

        $tester = new Validation\StructuredDataTester;

        return $tester->checkRichResultsEligibility($this->schema_markup);
    }
}
