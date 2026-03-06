<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang;

use MessageFormatter;

/**
 * ICU Message Format support for complex translations.
 *
 * Provides support for ICU MessageFormat syntax which enables:
 * - Plural forms: {count, plural, one{# item} other{# items}}
 * - Select/gender: {gender, select, male{He} female{She} other{They}}
 * - Number formatting: {amount, number, currency}
 * - Date/time formatting: {date, date, long}
 * - Nested messages for complex scenarios
 *
 * Usage:
 *   $formatter = new IcuMessageFormatter('en_GB');
 *   $message = $formatter->format(
 *       '{count, plural, =0{no items} one{# item} other{# items}}',
 *       ['count' => 5]
 *   );
 *
 * Configuration in config/core.php:
 *   'lang' => [
 *       'icu_enabled' => true,  // Enable ICU message format support
 *   ]
 *
 * @see https://unicode-org.github.io/icu/userguide/format_parse/messages/
 */
class IcuMessageFormatter
{
    /**
     * Whether the intl extension is available.
     */
    protected bool $intlAvailable;

    /**
     * The locale to use for formatting.
     */
    protected string $locale;

    /**
     * Cache of compiled message formatters.
     *
     * @var array<string, MessageFormatter|null>
     */
    protected array $formatterCache = [];

    /**
     * Maximum cache size to prevent memory leaks.
     */
    protected const MAX_CACHE_SIZE = 100;

    /**
     * Create a new ICU message formatter.
     */
    public function __construct(?string $locale = null)
    {
        $this->locale = $locale ?? app()->getLocale();
        $this->intlAvailable = extension_loaded('intl') && class_exists(MessageFormatter::class);
    }

    /**
     * Check if ICU message formatting is available.
     */
    public function isAvailable(): bool
    {
        return $this->intlAvailable;
    }

    /**
     * Format a message using ICU MessageFormat syntax.
     *
     * @param  string  $pattern  The ICU message pattern
     * @param  array<string, mixed>  $args  Arguments to substitute
     * @return string The formatted message
     */
    public function format(string $pattern, array $args = []): string
    {
        if (! $this->intlAvailable) {
            return $this->fallbackFormat($pattern, $args);
        }

        $formatter = $this->getFormatter($pattern);

        if ($formatter === null) {
            // If pattern is invalid, try fallback formatting
            return $this->fallbackFormat($pattern, $args);
        }

        $result = $formatter->format($args);

        if ($result === false) {
            // Format failed, use fallback
            return $this->fallbackFormat($pattern, $args);
        }

        return $result;
    }

    /**
     * Format a message with the given locale.
     *
     * @param  string  $locale  The locale to use
     * @param  string  $pattern  The ICU message pattern
     * @param  array<string, mixed>  $args  Arguments to substitute
     * @return string The formatted message
     */
    public function formatWithLocale(string $locale, string $pattern, array $args = []): string
    {
        $originalLocale = $this->locale;
        $this->locale = $locale;

        try {
            // Don't use cache for locale-specific formatting
            if (! $this->intlAvailable) {
                return $this->fallbackFormat($pattern, $args);
            }

            $formatter = MessageFormatter::create($locale, $pattern);

            if ($formatter === null) {
                return $this->fallbackFormat($pattern, $args);
            }

            $result = $formatter->format($args);

            return $result !== false ? $result : $this->fallbackFormat($pattern, $args);
        } finally {
            $this->locale = $originalLocale;
        }
    }

    /**
     * Get or create a message formatter for the pattern.
     */
    protected function getFormatter(string $pattern): ?MessageFormatter
    {
        $cacheKey = $this->locale.':'.$pattern;

        if (array_key_exists($cacheKey, $this->formatterCache)) {
            return $this->formatterCache[$cacheKey];
        }

        // Prevent cache from growing too large
        if (count($this->formatterCache) >= self::MAX_CACHE_SIZE) {
            // Remove oldest entries (first half)
            $this->formatterCache = array_slice(
                $this->formatterCache,
                (int) (self::MAX_CACHE_SIZE / 2),
                null,
                true
            );
        }

        $formatter = MessageFormatter::create($this->locale, $pattern);
        $this->formatterCache[$cacheKey] = $formatter;

        return $formatter;
    }

    /**
     * Fallback formatting when intl is unavailable or pattern is invalid.
     *
     * Provides basic placeholder replacement for simple cases.
     */
    protected function fallbackFormat(string $pattern, array $args): string
    {
        // Simple placeholder replacement for {name} syntax
        return preg_replace_callback(
            '/\{(\w+)(?:,[^}]*)?\}/',
            function ($matches) use ($args) {
                $key = $matches[1];

                if (isset($args[$key])) {
                    return (string) $args[$key];
                }

                // Return the original placeholder if no replacement found
                return $matches[0];
            },
            $pattern
        ) ?? $pattern;
    }

    /**
     * Validate an ICU message pattern.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validate(string $pattern): array
    {
        if (! $this->intlAvailable) {
            return [
                'valid' => true,
                'error' => null,
            ];
        }

        $formatter = MessageFormatter::create($this->locale, $pattern);

        if ($formatter === null) {
            return [
                'valid' => false,
                'error' => intl_get_error_message(),
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Set the locale for formatting.
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get the current locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Clear the formatter cache.
     */
    public function clearCache(): void
    {
        $this->formatterCache = [];
    }

    /**
     * Create a plural message pattern.
     *
     * Helper method to create proper ICU plural patterns.
     *
     * @param  string  $variable  The variable name
     * @param  array<string, string>  $forms  Plural forms (zero, one, two, few, many, other)
     * @return string The ICU pattern
     */
    public static function plural(string $variable, array $forms): string
    {
        $parts = [];

        foreach ($forms as $key => $text) {
            // Support both =0 style and named (zero, one, other) styles
            if (is_numeric($key)) {
                $parts[] = "={$key}{{$text}}";
            } else {
                $parts[] = "{$key}{{$text}}";
            }
        }

        return '{'.$variable.', plural, '.implode(' ', $parts).'}';
    }

    /**
     * Create a select message pattern.
     *
     * Helper method to create proper ICU select patterns.
     *
     * @param  string  $variable  The variable name
     * @param  array<string, string>  $options  Select options including 'other'
     * @return string The ICU pattern
     */
    public static function select(string $variable, array $options): string
    {
        $parts = [];

        foreach ($options as $key => $text) {
            $parts[] = "{$key}{{$text}}";
        }

        return '{'.$variable.', select, '.implode(' ', $parts).'}';
    }
}
