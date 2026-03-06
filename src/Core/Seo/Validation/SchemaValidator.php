<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Validation;

/**
 * Validates JSON-LD schema against schema.org specifications.
 *
 * Performs basic validation of required properties for common schema types.
 * This is not a complete schema.org validator but covers the most common cases.
 */
class SchemaValidator
{
    /**
     * Required properties for each schema type.
     *
     * @var array<string, array<string>>
     */
    private const REQUIRED_PROPERTIES = [
        'Article' => ['headline'],
        'TechArticle' => ['headline'],
        'BlogPosting' => ['headline'],
        'NewsArticle' => ['headline', 'datePublished'],
        'HowTo' => ['name', 'step'],
        'HowToStep' => ['text'],
        'FAQPage' => ['mainEntity'],
        'Question' => ['name', 'acceptedAnswer'],
        'Answer' => ['text'],
        'BreadcrumbList' => ['itemListElement'],
        'ListItem' => ['position'],
        'Organization' => ['name'],
        'Person' => ['name'],
        'WebSite' => ['name', 'url'],
        'WebPage' => [],
        'ImageObject' => ['url'],
        'SoftwareApplication' => ['name'],
        'LocalBusiness' => ['name'],
        'Product' => ['name'],
        'Offer' => ['price'],
        'AggregateRating' => ['ratingValue'],
        'Review' => ['reviewBody'],
        'Event' => ['name', 'startDate'],
        'Place' => ['name'],
        'PostalAddress' => [],
        'SearchAction' => ['target'],
        'EntryPoint' => ['urlTemplate'],
    ];

    /**
     * Recommended properties for better SEO.
     *
     * @var array<string, array<string>>
     */
    private const RECOMMENDED_PROPERTIES = [
        'Article' => ['author', 'datePublished', 'dateModified', 'description', 'image'],
        'TechArticle' => ['author', 'datePublished', 'dateModified', 'description'],
        'BlogPosting' => ['author', 'datePublished', 'dateModified', 'description', 'image'],
        'HowTo' => ['description', 'totalTime'],
        'Organization' => ['url', 'logo'],
        'Product' => ['description', 'image', 'offers'],
        'LocalBusiness' => ['address', 'telephone'],
    ];

    /**
     * Valid schema.org context URLs.
     *
     * @var array<string>
     */
    private const VALID_CONTEXTS = [
        'https://schema.org',
        'http://schema.org',
        'https://schema.org/',
        'http://schema.org/',
    ];

    /**
     * Validate a schema array.
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public static function validate(array $schema): array
    {
        $errors = [];
        $warnings = [];

        // Check for @graph structure
        if (isset($schema['@graph'])) {
            $contextResult = self::validateContext($schema);
            $errors = array_merge($errors, $contextResult);

            foreach ($schema['@graph'] as $index => $item) {
                $itemResult = self::validateSchemaItem($item, "graph[$index]");
                $errors = array_merge($errors, $itemResult['errors']);
                $warnings = array_merge($warnings, $itemResult['warnings']);
            }
        } else {
            $itemResult = self::validateSchemaItem($schema, 'root');
            $errors = array_merge($errors, $itemResult['errors']);
            $warnings = array_merge($warnings, $itemResult['warnings']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate schema context.
     *
     * @return array<string>
     */
    private static function validateContext(array $schema): array
    {
        $errors = [];

        if (! isset($schema['@context'])) {
            $errors[] = 'Missing @context property';
        } elseif (! in_array($schema['@context'], self::VALID_CONTEXTS, true)) {
            $errors[] = 'Invalid @context: must be https://schema.org';
        }

        return $errors;
    }

    /**
     * Validate a single schema item.
     *
     * @return array{errors: array<string>, warnings: array<string>}
     */
    private static function validateSchemaItem(array $item, string $path): array
    {
        $errors = [];
        $warnings = [];

        // Check for @type
        if (! isset($item['@type'])) {
            $errors[] = "$path: Missing @type property";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $type = $item['@type'];

        // Check required properties
        if (isset(self::REQUIRED_PROPERTIES[$type])) {
            foreach (self::REQUIRED_PROPERTIES[$type] as $property) {
                if (! isset($item[$property]) || self::isEmpty($item[$property])) {
                    $errors[] = "$path ($type): Missing required property '$property'";
                }
            }
        }

        // Check recommended properties
        if (isset(self::RECOMMENDED_PROPERTIES[$type])) {
            foreach (self::RECOMMENDED_PROPERTIES[$type] as $property) {
                if (! isset($item[$property]) || self::isEmpty($item[$property])) {
                    $warnings[] = "$path ($type): Missing recommended property '$property'";
                }
            }
        }

        // Validate nested objects
        foreach ($item as $key => $value) {
            if (str_starts_with($key, '@')) {
                continue;
            }

            if (is_array($value)) {
                if (isset($value['@type'])) {
                    $nestedResult = self::validateSchemaItem($value, "$path.$key");
                    $errors = array_merge($errors, $nestedResult['errors']);
                    $warnings = array_merge($warnings, $nestedResult['warnings']);
                } elseif (self::isIndexedArray($value)) {
                    foreach ($value as $index => $nestedItem) {
                        if (is_array($nestedItem) && isset($nestedItem['@type'])) {
                            $nestedResult = self::validateSchemaItem($nestedItem, "$path.$key[$index]");
                            $errors = array_merge($errors, $nestedResult['errors']);
                            $warnings = array_merge($warnings, $nestedResult['warnings']);
                        }
                    }
                }
            }
        }

        // Type-specific validation
        $typeErrors = self::validateTypeSpecific($item, $type, $path);
        $errors = array_merge($errors, $typeErrors);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Type-specific validation rules.
     *
     * @return array<string>
     */
    private static function validateTypeSpecific(array $item, string $type, string $path): array
    {
        $errors = [];

        switch ($type) {
            case 'Article':
            case 'TechArticle':
            case 'BlogPosting':
            case 'NewsArticle':
                if (isset($item['headline']) && strlen($item['headline']) > 110) {
                    $errors[] = "$path ($type): headline should be 110 characters or fewer";
                }
                if (isset($item['datePublished']) && ! self::isValidIso8601($item['datePublished'])) {
                    $errors[] = "$path ($type): datePublished must be valid ISO 8601 format";
                }
                if (isset($item['dateModified']) && ! self::isValidIso8601($item['dateModified'])) {
                    $errors[] = "$path ($type): dateModified must be valid ISO 8601 format";
                }
                break;

            case 'HowTo':
                if (isset($item['step']) && ! is_array($item['step'])) {
                    $errors[] = "$path ($type): step must be an array";
                } elseif (isset($item['step']) && empty($item['step'])) {
                    $errors[] = "$path ($type): step array cannot be empty";
                }
                if (isset($item['totalTime']) && ! self::isValidIsoDuration($item['totalTime'])) {
                    $errors[] = "$path ($type): totalTime must be valid ISO 8601 duration (e.g., PT30M)";
                }
                break;

            case 'HowToStep':
                if (isset($item['position']) && (! is_int($item['position']) || $item['position'] < 1)) {
                    $errors[] = "$path ($type): position must be a positive integer";
                }
                break;

            case 'ListItem':
                if (isset($item['position']) && (! is_int($item['position']) || $item['position'] < 1)) {
                    $errors[] = "$path ($type): position must be a positive integer";
                }
                break;

            case 'Offer':
                if (isset($item['price']) && ! is_numeric($item['price']) && $item['price'] !== '0') {
                    $errors[] = "$path ($type): price must be numeric";
                }
                break;

            case 'AggregateRating':
                if (isset($item['ratingValue'])) {
                    $rating = (float) $item['ratingValue'];
                    if ($rating < 0 || $rating > 5) {
                        $errors[] = "$path ($type): ratingValue should be between 0 and 5";
                    }
                }
                break;

            case 'ImageObject':
                if (isset($item['url']) && ! filter_var($item['url'], FILTER_VALIDATE_URL)) {
                    $errors[] = "$path ($type): url must be a valid URL";
                }
                break;
        }

        return $errors;
    }

    /**
     * Check if a value is empty (null, empty string, or empty array).
     */
    private static function isEmpty(mixed $value): bool
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
    private static function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Validate ISO 8601 date format.
     */
    private static function isValidIso8601(string $date): bool
    {
        // Match common ISO 8601 formats
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/',                           // 2024-01-15
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',          // 2024-01-15T10:30:00
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', // With timezone offset
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',        // UTC
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate ISO 8601 duration format.
     */
    private static function isValidIsoDuration(string $duration): bool
    {
        // Match ISO 8601 duration format: P[n]Y[n]M[n]DT[n]H[n]M[n]S
        return (bool) preg_match('/^P(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+S)?)?$/', $duration);
    }
}
