<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Validation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Structured Data Testing Service.
 *
 * Validates JSON-LD structured data against schema.org specifications
 * and optionally integrates with external validation APIs like Google's
 * Rich Results Test.
 *
 * Features:
 * - Local validation against schema.org specs
 * - External API validation (Google Rich Results Test)
 * - Actionable feedback on issues with fix suggestions
 * - Support for common schema types (Article, Product, FAQ, etc.)
 * - Rich results eligibility checking
 *
 * Configuration in config/seo.php:
 *   'structured_data' => [
 *       'external_validation' => false,
 *       'google_api_key' => null,
 *       'cache_validation' => true,
 *       'cache_ttl' => 3600,
 *   ]
 *
 * Usage:
 *   $tester = new StructuredDataTester();
 *   $result = $tester->test($schema);
 *   $result = $tester->testUrl('https://example.com/page');
 *   $richResults = $tester->checkRichResultsEligibility($schema);
 */
class StructuredDataTester
{
    /**
     * Schema.org context URLs.
     *
     * @var array<string>
     */
    protected const VALID_CONTEXTS = [
        'https://schema.org',
        'http://schema.org',
        'https://schema.org/',
        'http://schema.org/',
    ];

    /**
     * Rich results eligible types and their requirements.
     *
     * @var array<string, array{required: array<string>, recommended: array<string>, features: array<string>}>
     */
    protected const RICH_RESULTS_TYPES = [
        'Article' => [
            'required' => ['headline', 'author', 'datePublished'],
            'recommended' => ['image', 'dateModified', 'publisher'],
            'features' => ['Article rich result', 'Top stories', 'Visual stories'],
        ],
        'Product' => [
            'required' => ['name'],
            'recommended' => ['image', 'description', 'offers', 'aggregateRating', 'review'],
            'features' => ['Product rich result', 'Merchant listings', 'Product snippets'],
        ],
        'FAQPage' => [
            'required' => ['mainEntity'],
            'recommended' => [],
            'features' => ['FAQ rich result'],
        ],
        'HowTo' => [
            'required' => ['name', 'step'],
            'recommended' => ['totalTime', 'supply', 'tool', 'image'],
            'features' => ['How-to rich result'],
        ],
        'Recipe' => [
            'required' => ['name', 'image'],
            'recommended' => ['author', 'prepTime', 'cookTime', 'nutrition', 'recipeIngredient', 'recipeInstructions'],
            'features' => ['Recipe rich result', 'Recipe carousel'],
        ],
        'Review' => [
            'required' => ['itemReviewed', 'reviewRating', 'author'],
            'recommended' => ['datePublished', 'reviewBody'],
            'features' => ['Review snippet'],
        ],
        'Event' => [
            'required' => ['name', 'startDate', 'location'],
            'recommended' => ['image', 'description', 'endDate', 'performer', 'offers'],
            'features' => ['Event rich result'],
        ],
        'LocalBusiness' => [
            'required' => ['name', 'address'],
            'recommended' => ['telephone', 'openingHours', 'image', 'priceRange', 'geo'],
            'features' => ['Local business panel', 'Knowledge panel'],
        ],
        'Organization' => [
            'required' => ['name'],
            'recommended' => ['url', 'logo', 'contactPoint', 'sameAs'],
            'features' => ['Knowledge panel', 'Logo in search results'],
        ],
        'BreadcrumbList' => [
            'required' => ['itemListElement'],
            'recommended' => [],
            'features' => ['Breadcrumb navigation in search results'],
        ],
        'VideoObject' => [
            'required' => ['name', 'description', 'thumbnailUrl', 'uploadDate'],
            'recommended' => ['contentUrl', 'duration', 'embedUrl'],
            'features' => ['Video rich result', 'Video carousel'],
        ],
        'WebSite' => [
            'required' => ['name', 'url'],
            'recommended' => ['potentialAction'],
            'features' => ['Sitelinks search box'],
        ],
        'JobPosting' => [
            'required' => ['title', 'description', 'datePosted', 'hiringOrganization', 'jobLocation'],
            'recommended' => ['validThrough', 'employmentType', 'baseSalary'],
            'features' => ['Job posting rich result'],
        ],
        'Course' => [
            'required' => ['name', 'provider'],
            'recommended' => ['description', 'offers'],
            'features' => ['Course rich result'],
        ],
    ];

    /**
     * Common schema errors and their fixes.
     *
     * @var array<string, array{explanation: string, fix: string}>
     */
    protected const ERROR_FIXES = [
        'missing_context' => [
            'explanation' => 'The @context property is required to identify the vocabulary.',
            'fix' => 'Add "@context": "https://schema.org" at the top level of your schema.',
        ],
        'invalid_context' => [
            'explanation' => 'The @context must be a valid schema.org URL.',
            'fix' => 'Change @context to "https://schema.org".',
        ],
        'missing_type' => [
            'explanation' => 'The @type property is required to specify the schema type.',
            'fix' => 'Add "@type": "YourSchemaType" to specify the type (e.g., "Article", "Product").',
        ],
        'invalid_date' => [
            'explanation' => 'Dates must be in ISO 8601 format.',
            'fix' => 'Use the format YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS for dates.',
        ],
        'headline_too_long' => [
            'explanation' => 'Google recommends headlines under 110 characters.',
            'fix' => 'Shorten your headline to 110 characters or less for best display.',
        ],
        'missing_image' => [
            'explanation' => 'Images are required for many rich results.',
            'fix' => 'Add an "image" property with a URL or ImageObject.',
        ],
        'invalid_url' => [
            'explanation' => 'URLs must be valid and accessible.',
            'fix' => 'Ensure the URL starts with https:// and is publicly accessible.',
        ],
        'missing_author' => [
            'explanation' => 'Author information helps establish content credibility.',
            'fix' => 'Add an "author" property with Person or Organization type.',
        ],
        'empty_array' => [
            'explanation' => 'Array properties should not be empty.',
            'fix' => 'Either add items to the array or remove the property.',
        ],
        'invalid_rating' => [
            'explanation' => 'Rating values should be within the expected range (typically 1-5).',
            'fix' => 'Ensure ratingValue is between 1 and 5, or specify bestRating/worstRating.',
        ],
    ];

    /**
     * Whether external validation is enabled.
     */
    protected bool $externalValidation;

    /**
     * Google API key for Rich Results Test.
     */
    protected ?string $googleApiKey;

    /**
     * Whether to cache validation results.
     */
    protected bool $cacheValidation;

    /**
     * Cache TTL in seconds.
     */
    protected int $cacheTtl;

    public function __construct()
    {
        $this->externalValidation = config('seo.structured_data.external_validation', false);
        $this->googleApiKey = config('seo.structured_data.google_api_key');
        $this->cacheValidation = config('seo.structured_data.cache_validation', true);
        $this->cacheTtl = config('seo.structured_data.cache_ttl', 3600);
    }

    /**
     * Test structured data against schema.org specifications.
     *
     * @param  array<string, mixed>  $schema  The schema to validate
     * @return array{
     *     valid: bool,
     *     errors: array<array{code: string, message: string, path: string, fix: string}>,
     *     warnings: array<array{code: string, message: string, path: string, fix: string}>,
     *     info: array<string>,
     *     rich_results: array<string>,
     *     types_found: array<string>
     * }
     */
    public function test(array $schema): array
    {
        $cacheKey = $this->cacheValidation ? 'seo_sd_test:'.md5(json_encode($schema)) : null;

        if ($cacheKey && $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $errors = [];
        $warnings = [];
        $info = [];
        $typesFound = [];

        // Validate context
        $contextResult = $this->validateContext($schema);
        $errors = array_merge($errors, $contextResult['errors']);
        $warnings = array_merge($warnings, $contextResult['warnings']);

        // Check for @graph structure
        if (isset($schema['@graph'])) {
            foreach ($schema['@graph'] as $index => $item) {
                $itemResult = $this->validateSchemaItem($item, "graph[$index]");
                $errors = array_merge($errors, $itemResult['errors']);
                $warnings = array_merge($warnings, $itemResult['warnings']);

                if (isset($item['@type'])) {
                    $typesFound[] = $item['@type'];
                }
            }
        } else {
            $itemResult = $this->validateSchemaItem($schema, 'root');
            $errors = array_merge($errors, $itemResult['errors']);
            $warnings = array_merge($warnings, $itemResult['warnings']);

            if (isset($schema['@type'])) {
                $typesFound[] = $schema['@type'];
            }
        }

        // Check rich results eligibility
        $richResults = $this->checkRichResultsEligibility($schema);

        // Add info messages
        $typesFound = array_unique($typesFound);
        if (! empty($typesFound)) {
            $info[] = 'Schema types found: '.implode(', ', $typesFound);
        }

        if (! empty($richResults)) {
            $info[] = 'Eligible for rich results: '.implode(', ', $richResults);
        }

        $result = [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'rich_results' => $richResults,
            'types_found' => $typesFound,
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Test a URL for structured data.
     *
     * Fetches the page and extracts JSON-LD scripts for validation.
     *
     * @return array{
     *     valid: bool,
     *     schemas_found: int,
     *     results: array<array{valid: bool, errors: array, warnings: array, info: array}>,
     *     summary: array{total_errors: int, total_warnings: int}
     * }
     */
    public function testUrl(string $url): array
    {
        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return [
                    'valid' => false,
                    'schemas_found' => 0,
                    'results' => [[
                        'valid' => false,
                        'errors' => [[
                            'code' => 'fetch_failed',
                            'message' => 'Failed to fetch URL: '.$response->status(),
                            'path' => 'url',
                            'fix' => 'Ensure the URL is accessible and returns a 200 status.',
                        ]],
                        'warnings' => [],
                        'info' => [],
                    ]],
                    'summary' => ['total_errors' => 1, 'total_warnings' => 0],
                ];
            }

            $schemas = $this->extractJsonLd($response->body());

            if (empty($schemas)) {
                return [
                    'valid' => true,
                    'schemas_found' => 0,
                    'results' => [],
                    'summary' => ['total_errors' => 0, 'total_warnings' => 0],
                ];
            }

            $results = [];
            $totalErrors = 0;
            $totalWarnings = 0;
            $allValid = true;

            foreach ($schemas as $index => $schema) {
                $result = $this->test($schema);
                $results[] = $result;

                $totalErrors += count($result['errors']);
                $totalWarnings += count($result['warnings']);

                if (! $result['valid']) {
                    $allValid = false;
                }
            }

            return [
                'valid' => $allValid,
                'schemas_found' => count($schemas),
                'results' => $results,
                'summary' => [
                    'total_errors' => $totalErrors,
                    'total_warnings' => $totalWarnings,
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to test URL for structured data', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'schemas_found' => 0,
                'results' => [[
                    'valid' => false,
                    'errors' => [[
                        'code' => 'exception',
                        'message' => 'Error testing URL: '.$e->getMessage(),
                        'path' => 'url',
                        'fix' => 'Check that the URL is valid and accessible.',
                    ]],
                    'warnings' => [],
                    'info' => [],
                ]],
                'summary' => ['total_errors' => 1, 'total_warnings' => 0],
            ];
        }
    }

    /**
     * Check which rich results the schema is eligible for.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string> List of eligible rich result features
     */
    public function checkRichResultsEligibility(array $schema): array
    {
        $eligible = [];

        // Collect all types in the schema
        $types = $this->collectTypes($schema);

        foreach ($types as $type) {
            if (isset(self::RICH_RESULTS_TYPES[$type])) {
                $config = self::RICH_RESULTS_TYPES[$type];
                $item = $this->findItemByType($schema, $type);

                if ($item === null) {
                    continue;
                }

                // Check if all required properties are present
                $hasRequired = true;
                foreach ($config['required'] as $prop) {
                    if (! isset($item[$prop]) || $this->isEmpty($item[$prop])) {
                        $hasRequired = false;
                        break;
                    }
                }

                if ($hasRequired) {
                    $eligible = array_merge($eligible, $config['features']);
                }
            }
        }

        return array_unique($eligible);
    }

    /**
     * Get detailed requirements for a schema type.
     *
     * @return array{
     *     required: array<string>,
     *     recommended: array<string>,
     *     features: array<string>,
     *     documentation_url: string
     * }|null
     */
    public function getTypeRequirements(string $type): ?array
    {
        if (! isset(self::RICH_RESULTS_TYPES[$type])) {
            return null;
        }

        $config = self::RICH_RESULTS_TYPES[$type];
        $config['documentation_url'] = 'https://schema.org/'.$type;

        return $config;
    }

    /**
     * Get actionable feedback for a validation error.
     *
     * @return array{explanation: string, fix: string}
     */
    public function getFix(string $errorCode): array
    {
        return self::ERROR_FIXES[$errorCode] ?? [
            'explanation' => 'An issue was detected with the structured data.',
            'fix' => 'Review the error message and consult schema.org documentation.',
        ];
    }

    /**
     * Validate with Google Rich Results Test API.
     *
     * Requires a Google API key with Rich Results Test API enabled.
     *
     * @param  string  $content  HTML content or URL
     * @param  bool  $isUrl  Whether content is a URL
     * @return array{
     *     success: bool,
     *     testStatus: string|null,
     *     richResultsDetected: array<string>,
     *     issues: array<array{severity: string, message: string}>,
     *     raw: array|null
     * }
     */
    public function validateWithGoogle(string $content, bool $isUrl = false): array
    {
        if (! $this->externalValidation || ! $this->googleApiKey) {
            return [
                'success' => false,
                'testStatus' => 'disabled',
                'richResultsDetected' => [],
                'issues' => [[
                    'severity' => 'info',
                    'message' => 'External validation is disabled or API key not configured.',
                ]],
                'raw' => null,
            ];
        }

        try {
            $endpoint = 'https://searchconsole.googleapis.com/v1/urlTestingTools/mobileFriendlyTest:run';

            $payload = $isUrl
                ? ['url' => $content]
                : ['requestScreenshot' => false];

            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Bearer '.$this->googleApiKey])
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'testStatus' => 'error',
                    'richResultsDetected' => [],
                    'issues' => [[
                        'severity' => 'error',
                        'message' => 'Google API request failed: '.$response->status(),
                    ]],
                    'raw' => null,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'testStatus' => $data['testStatus'] ?? 'unknown',
                'richResultsDetected' => $data['richResults'] ?? [],
                'issues' => $data['issues'] ?? [],
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::warning('Google Rich Results Test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'testStatus' => 'exception',
                'richResultsDetected' => [],
                'issues' => [[
                    'severity' => 'error',
                    'message' => 'API request failed: '.$e->getMessage(),
                ]],
                'raw' => null,
            ];
        }
    }

    /**
     * Generate a report with all validation results and suggestions.
     *
     * @param  array<string, mixed>  $schema
     * @return array{
     *     summary: array{valid: bool, error_count: int, warning_count: int},
     *     types: array<string>,
     *     rich_results: array<string>,
     *     errors: array<array{code: string, message: string, path: string, explanation: string, fix: string}>,
     *     warnings: array<array{code: string, message: string, path: string, explanation: string, fix: string}>,
     *     recommendations: array<string>,
     *     score: int
     * }
     */
    public function generateReport(array $schema): array
    {
        $testResult = $this->test($schema);

        // Enrich errors and warnings with fixes
        $enrichedErrors = array_map(function ($error) {
            $fix = $this->getFix($error['code']);

            return array_merge($error, $fix);
        }, $testResult['errors']);

        $enrichedWarnings = array_map(function ($warning) {
            $fix = $this->getFix($warning['code']);

            return array_merge($warning, $fix);
        }, $testResult['warnings']);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($schema, $testResult);

        // Calculate a quality score (0-100)
        $score = $this->calculateScore($testResult);

        return [
            'summary' => [
                'valid' => $testResult['valid'],
                'error_count' => count($testResult['errors']),
                'warning_count' => count($testResult['warnings']),
            ],
            'types' => $testResult['types_found'],
            'rich_results' => $testResult['rich_results'],
            'errors' => $enrichedErrors,
            'warnings' => $enrichedWarnings,
            'recommendations' => $recommendations,
            'score' => $score,
        ];
    }

    /**
     * Validate schema context.
     *
     * @return array{errors: array, warnings: array}
     */
    protected function validateContext(array $schema): array
    {
        $errors = [];
        $warnings = [];

        // Context might be at root or in @graph items
        if (isset($schema['@graph'])) {
            if (! isset($schema['@context'])) {
                $errors[] = [
                    'code' => 'missing_context',
                    'message' => 'Missing @context property',
                    'path' => 'root',
                    'fix' => self::ERROR_FIXES['missing_context']['fix'],
                ];
            } elseif (! in_array($schema['@context'], self::VALID_CONTEXTS, true)) {
                $errors[] = [
                    'code' => 'invalid_context',
                    'message' => 'Invalid @context: must be https://schema.org',
                    'path' => 'root.@context',
                    'fix' => self::ERROR_FIXES['invalid_context']['fix'],
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate a schema item.
     *
     * @return array{errors: array, warnings: array}
     */
    protected function validateSchemaItem(array $item, string $path): array
    {
        $errors = [];
        $warnings = [];

        // Check for @type
        if (! isset($item['@type'])) {
            $errors[] = [
                'code' => 'missing_type',
                'message' => 'Missing @type property',
                'path' => $path,
                'fix' => self::ERROR_FIXES['missing_type']['fix'],
            ];

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $type = $item['@type'];

        // Validate type-specific requirements
        $typeResult = $this->validateTypeRequirements($item, $type, $path);
        $errors = array_merge($errors, $typeResult['errors']);
        $warnings = array_merge($warnings, $typeResult['warnings']);

        // Validate common properties
        $commonResult = $this->validateCommonProperties($item, $type, $path);
        $errors = array_merge($errors, $commonResult['errors']);
        $warnings = array_merge($warnings, $commonResult['warnings']);

        // Recursively validate nested items
        foreach ($item as $key => $value) {
            if (str_starts_with($key, '@')) {
                continue;
            }

            if (is_array($value)) {
                if (isset($value['@type'])) {
                    $nestedResult = $this->validateSchemaItem($value, "$path.$key");
                    $errors = array_merge($errors, $nestedResult['errors']);
                    $warnings = array_merge($warnings, $nestedResult['warnings']);
                } elseif ($this->isIndexedArray($value)) {
                    foreach ($value as $index => $nested) {
                        if (is_array($nested) && isset($nested['@type'])) {
                            $nestedResult = $this->validateSchemaItem($nested, "$path.$key[$index]");
                            $errors = array_merge($errors, $nestedResult['errors']);
                            $warnings = array_merge($warnings, $nestedResult['warnings']);
                        }
                    }
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate type-specific requirements.
     *
     * @return array{errors: array, warnings: array}
     */
    protected function validateTypeRequirements(array $item, string $type, string $path): array
    {
        $errors = [];
        $warnings = [];

        if (! isset(self::RICH_RESULTS_TYPES[$type])) {
            // Use SchemaValidator for types not in rich results
            $result = SchemaValidator::validate(['@context' => 'https://schema.org', '@type' => $type] + $item);
            foreach ($result['errors'] as $error) {
                $errors[] = [
                    'code' => 'schema_validation',
                    'message' => $error,
                    'path' => $path,
                    'fix' => 'Review the schema.org documentation for '.$type,
                ];
            }
            foreach ($result['warnings'] as $warning) {
                $warnings[] = [
                    'code' => 'schema_recommendation',
                    'message' => $warning,
                    'path' => $path,
                    'fix' => 'Consider adding the recommended property for better SEO.',
                ];
            }

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $config = self::RICH_RESULTS_TYPES[$type];

        // Check required properties
        foreach ($config['required'] as $prop) {
            if (! isset($item[$prop]) || $this->isEmpty($item[$prop])) {
                $errors[] = [
                    'code' => 'missing_required',
                    'message' => "$type: Missing required property '$prop'",
                    'path' => "$path.$prop",
                    'fix' => "Add the '$prop' property to enable rich results.",
                ];
            }
        }

        // Check recommended properties
        foreach ($config['recommended'] as $prop) {
            if (! isset($item[$prop]) || $this->isEmpty($item[$prop])) {
                $warnings[] = [
                    'code' => 'missing_recommended',
                    'message' => "$type: Missing recommended property '$prop'",
                    'path' => "$path.$prop",
                    'fix' => "Consider adding '$prop' for better rich result display.",
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate common property formats.
     *
     * @return array{errors: array, warnings: array}
     */
    protected function validateCommonProperties(array $item, string $type, string $path): array
    {
        $errors = [];
        $warnings = [];

        // Validate dates
        foreach (['datePublished', 'dateModified', 'dateCreated', 'startDate', 'endDate'] as $dateProp) {
            if (isset($item[$dateProp]) && ! $this->isValidDate($item[$dateProp])) {
                $errors[] = [
                    'code' => 'invalid_date',
                    'message' => "$dateProp must be in ISO 8601 format",
                    'path' => "$path.$dateProp",
                    'fix' => self::ERROR_FIXES['invalid_date']['fix'],
                ];
            }
        }

        // Validate URLs
        foreach (['url', 'sameAs', 'mainEntityOfPage'] as $urlProp) {
            if (isset($item[$urlProp])) {
                $urls = is_array($item[$urlProp]) ? $item[$urlProp] : [$item[$urlProp]];
                foreach ($urls as $url) {
                    if (is_string($url) && ! filter_var($url, FILTER_VALIDATE_URL)) {
                        $errors[] = [
                            'code' => 'invalid_url',
                            'message' => "$urlProp contains an invalid URL",
                            'path' => "$path.$urlProp",
                            'fix' => self::ERROR_FIXES['invalid_url']['fix'],
                        ];
                    }
                }
            }
        }

        // Validate headline length for articles
        if (in_array($type, ['Article', 'TechArticle', 'BlogPosting', 'NewsArticle'], true)) {
            if (isset($item['headline']) && strlen($item['headline']) > 110) {
                $warnings[] = [
                    'code' => 'headline_too_long',
                    'message' => 'Headline exceeds 110 characters ('.strlen($item['headline']).')',
                    'path' => "$path.headline",
                    'fix' => self::ERROR_FIXES['headline_too_long']['fix'],
                ];
            }
        }

        // Validate ratings
        if (isset($item['ratingValue'])) {
            $rating = (float) $item['ratingValue'];
            $best = (float) ($item['bestRating'] ?? 5);
            $worst = (float) ($item['worstRating'] ?? 1);

            if ($rating < $worst || $rating > $best) {
                $errors[] = [
                    'code' => 'invalid_rating',
                    'message' => "ratingValue ($rating) is outside valid range ($worst-$best)",
                    'path' => "$path.ratingValue",
                    'fix' => self::ERROR_FIXES['invalid_rating']['fix'],
                ];
            }
        }

        // Validate image exists for types that need it
        $imageRequiredTypes = ['Article', 'Product', 'Recipe', 'Event', 'VideoObject'];
        if (in_array($type, $imageRequiredTypes, true)) {
            if (! isset($item['image']) || $this->isEmpty($item['image'])) {
                $warnings[] = [
                    'code' => 'missing_image',
                    'message' => "$type should include an image for rich results",
                    'path' => "$path.image",
                    'fix' => self::ERROR_FIXES['missing_image']['fix'],
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Extract JSON-LD scripts from HTML.
     *
     * @return array<int, array>
     */
    protected function extractJsonLd(string $html): array
    {
        $schemas = [];

        // Match all JSON-LD script tags
        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
            $html,
            $matches
        );

        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);
            if (is_array($decoded)) {
                $schemas[] = $decoded;
            }
        }

        return $schemas;
    }

    /**
     * Collect all @type values from a schema.
     *
     * @return array<string>
     */
    protected function collectTypes(array $schema): array
    {
        $types = [];

        if (isset($schema['@type'])) {
            $types[] = $schema['@type'];
        }

        if (isset($schema['@graph'])) {
            foreach ($schema['@graph'] as $item) {
                if (isset($item['@type'])) {
                    $types[] = $item['@type'];
                }
            }
        }

        // Check nested items
        foreach ($schema as $value) {
            if (is_array($value)) {
                if (isset($value['@type'])) {
                    $types[] = $value['@type'];
                } elseif ($this->isIndexedArray($value)) {
                    foreach ($value as $nested) {
                        if (is_array($nested) && isset($nested['@type'])) {
                            $types[] = $nested['@type'];
                        }
                    }
                }
            }
        }

        return array_unique($types);
    }

    /**
     * Find a schema item by type.
     *
     * @return array<string, mixed>|null
     */
    protected function findItemByType(array $schema, string $type): ?array
    {
        if (isset($schema['@type']) && $schema['@type'] === $type) {
            return $schema;
        }

        if (isset($schema['@graph'])) {
            foreach ($schema['@graph'] as $item) {
                if (isset($item['@type']) && $item['@type'] === $type) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Generate recommendations based on validation results.
     *
     * @return array<string>
     */
    protected function generateRecommendations(array $schema, array $testResult): array
    {
        $recommendations = [];

        // Check for missing types that could enhance SEO
        $types = $testResult['types_found'];

        if (! in_array('BreadcrumbList', $types, true)) {
            $recommendations[] = 'Consider adding BreadcrumbList schema to enable breadcrumb navigation in search results.';
        }

        if (! in_array('WebSite', $types, true) && ! in_array('Organization', $types, true)) {
            $recommendations[] = 'Consider adding Organization or WebSite schema to enhance your site\'s presence in search results.';
        }

        // If there are articles without FAQ
        if ((in_array('Article', $types, true) || in_array('BlogPosting', $types, true))
            && ! in_array('FAQPage', $types, true)) {
            $recommendations[] = 'If your content answers common questions, consider adding FAQPage schema.';
        }

        // Check for social profiles
        $org = $this->findItemByType($schema, 'Organization');
        if ($org && ! isset($org['sameAs'])) {
            $recommendations[] = 'Add sameAs property to Organization with links to your social media profiles.';
        }

        return $recommendations;
    }

    /**
     * Calculate a quality score for the structured data.
     */
    protected function calculateScore(array $testResult): int
    {
        $score = 100;

        // Deduct for errors (severe)
        $score -= count($testResult['errors']) * 15;

        // Deduct for warnings (moderate)
        $score -= count($testResult['warnings']) * 5;

        // Bonus for rich results eligibility
        $score += min(count($testResult['rich_results']) * 5, 20);

        return max(0, min(100, $score));
    }

    /**
     * Check if a value is empty.
     */
    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an array is indexed (not associative).
     */
    protected function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Validate ISO 8601 date format.
     */
    protected function isValidDate(string $date): bool
    {
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/',
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $date)) {
                return true;
            }
        }

        return false;
    }
}
