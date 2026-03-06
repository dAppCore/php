<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Input;

use Normalizer;
use Psr\Log\LoggerInterface;

/**
 * Input sanitiser - makes data safe, not valid.
 *
 * One job: strip dangerous control characters.
 * One C call: filter_var_array.
 *
 * Laravel validates. We sanitise.
 *
 * Features:
 * - Configurable filter rules per field via schema
 * - Unicode NFC normalization for consistent string handling
 * - Optional audit logging when content is modified
 * - Rich text support with safe HTML tags whitelist
 * - Configurable maximum input length enforcement
 * - Transformation hooks for custom processing at different stages
 *
 * ## Transformation Hooks
 *
 * Register callbacks to transform values at specific stages of the
 * sanitization pipeline:
 *
 * ```php
 * $sanitiser = (new Sanitiser())
 *     ->beforeFilter(function (string $value, string $field): string {
 *         // Transform before any filtering
 *         return trim($value);
 *     })
 *     ->afterFilter(function (string $value, string $field): string {
 *         // Transform after all filtering is complete
 *         return $value;
 *     })
 *     ->transformField('username', function (string $value): string {
 *         // Field-specific transformation
 *         return strtolower($value);
 *     });
 * ```
 *
 * Hook execution order:
 * 1. Before hooks (global, then field-specific)
 * 2. Standard filtering pipeline (normalize, strip, HTML, preset, filters, length)
 * 3. After hooks (global, then field-specific)
 */
class Sanitiser
{
    /**
     * Default safe HTML tags for rich text fields.
     * These tags are considered safe for user-generated content.
     */
    public const SAFE_HTML_TAGS = '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre>';

    /**
     * Minimal HTML tags for basic formatting.
     */
    public const BASIC_HTML_TAGS = '<p><br><strong><em>';

    /**
     * Default maximum input length (0 = unlimited).
     */
    public const DEFAULT_MAX_LENGTH = 0;

    /**
     * Common filter rule presets for quick configuration.
     * Use with withPreset() or field-level 'preset' schema option.
     */
    public const PRESET_EMAIL = 'email';

    public const PRESET_URL = 'url';

    public const PRESET_PHONE = 'phone';

    public const PRESET_ALPHA = 'alpha';

    public const PRESET_ALPHANUMERIC = 'alphanumeric';

    public const PRESET_NUMERIC = 'numeric';

    public const PRESET_SLUG = 'slug';

    /**
     * Schema for per-field filter rules.
     *
     * Format: ['field_name' => ['filters' => [...], 'options' => [...]]]
     *
     * @var array<string, array{filters?: int[], options?: int[], skip_control_strip?: bool, skip_normalize?: bool, allow_html?: string|bool, max_length?: int}>
     */
    protected array $schema = [];

    /**
     * Optional logger for audit logging.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Whether to enable audit logging.
     */
    protected bool $auditEnabled = false;

    /**
     * Whether to normalize Unicode to NFC form.
     */
    protected bool $normalizeUnicode = true;

    /**
     * Global maximum input length (0 = unlimited).
     */
    protected int $maxLength = 0;

    /**
     * Global allowed HTML tags (empty string = strip all HTML).
     */
    protected string $allowedHtmlTags = '';

    /**
     * Global before-filter transformation hooks.
     *
     * @var array<callable(string, string): string>
     */
    protected array $beforeHooks = [];

    /**
     * Global after-filter transformation hooks.
     *
     * @var array<callable(string, string): string>
     */
    protected array $afterHooks = [];

    /**
     * Field-specific transformation hooks (keyed by field name).
     *
     * @var array<string, array{before?: array<callable(string): string>, after?: array<callable(string): string>}>
     */
    protected array $fieldHooks = [];

    /**
     * Preset definitions for common input types.
     *
     * @var array<string, array{pattern?: string, filter?: int, transform?: callable(string): string}>
     */
    protected static array $presets = [
        self::PRESET_EMAIL => [
            'filter' => FILTER_SANITIZE_EMAIL,
            'transform' => 'strtolower',
        ],
        self::PRESET_URL => [
            'filter' => FILTER_SANITIZE_URL,
        ],
        self::PRESET_PHONE => [
            // Keep only digits, plus, hyphens, parentheses, and spaces
            'pattern' => '/[^\d\+\-\(\)\s]/',
        ],
        self::PRESET_ALPHA => [
            // Keep only letters (including Unicode)
            'pattern' => '/[^\p{L}]/u',
        ],
        self::PRESET_ALPHANUMERIC => [
            // Keep only letters and numbers (including Unicode)
            'pattern' => '/[^\p{L}\p{N}]/u',
        ],
        self::PRESET_NUMERIC => [
            // Keep only digits, decimal point, and minus sign
            'pattern' => '/[^\d\.\-]/',
        ],
        self::PRESET_SLUG => [
            // Convert to URL-safe slug format
            'pattern' => '/[^a-z0-9\-]/',
            'transform' => 'strtolower',
        ],
    ];

    /**
     * Create a new Sanitiser instance.
     *
     * @param  array<string, array{filters?: int[], options?: int[], skip_control_strip?: bool, skip_normalize?: bool, allow_html?: string|bool, max_length?: int}>  $schema  Per-field filter rules
     * @param  LoggerInterface|null  $logger  Optional PSR-3 logger for audit logging
     * @param  bool  $auditEnabled  Whether to enable audit logging (requires logger)
     * @param  bool  $normalizeUnicode  Whether to normalize Unicode to NFC form
     * @param  int  $maxLength  Global maximum input length (0 = unlimited)
     * @param  string  $allowedHtmlTags  Global allowed HTML tags (empty = strip all)
     */
    public function __construct(
        array $schema = [],
        ?LoggerInterface $logger = null,
        bool $auditEnabled = false,
        bool $normalizeUnicode = true,
        int $maxLength = 0,
        string $allowedHtmlTags = ''
    ) {
        $this->schema = $schema;
        $this->logger = $logger;
        $this->auditEnabled = $auditEnabled && $logger !== null;
        $this->normalizeUnicode = $normalizeUnicode;
        $this->maxLength = $maxLength;
        $this->allowedHtmlTags = $allowedHtmlTags;
    }

    /**
     * Set the per-field filter schema.
     *
     * Schema format:
     * [
     *     'field_name' => [
     *         'filters' => [FILTER_SANITIZE_EMAIL, ...], // Additional filters to apply
     *         'options' => [FILTER_FLAG_STRIP_HIGH, ...], // Additional flags
     *         'skip_control_strip' => false, // Skip control character stripping
     *         'skip_normalize' => false, // Skip Unicode normalization
     *         'allow_html' => '<p><br><strong>', // Allowed HTML tags for this field
     *         'max_length' => 1000, // Max length for this field (overrides global)
     *     ],
     * ]
     *
     * @param  array<string, array{filters?: int[], options?: int[], skip_control_strip?: bool, skip_normalize?: bool, allow_html?: string|bool, max_length?: int}>  $schema
     */
    public function withSchema(array $schema): static
    {
        $clone = clone $this;
        $clone->schema = $schema;

        return $clone;
    }

    /**
     * Set the logger for audit logging.
     *
     * @param  bool  $enabled  Whether to enable audit logging
     */
    public function withLogger(LoggerInterface $logger, bool $enabled = true): static
    {
        $clone = clone $this;
        $clone->logger = $logger;
        $clone->auditEnabled = $enabled;

        return $clone;
    }

    /**
     * Enable or disable Unicode NFC normalization.
     */
    public function withNormalization(bool $enabled): static
    {
        $clone = clone $this;
        $clone->normalizeUnicode = $enabled;

        return $clone;
    }

    /**
     * Set allowed HTML tags globally for all fields.
     *
     * Pass a string of allowed tags (e.g., '<p><br><strong>') or
     * use one of the predefined constants (SAFE_HTML_TAGS, BASIC_HTML_TAGS).
     *
     * @param  string  $allowedTags  Allowed HTML tags in strip_tags format
     */
    public function allowHtml(string $allowedTags = self::SAFE_HTML_TAGS): static
    {
        $clone = clone $this;
        $clone->allowedHtmlTags = $allowedTags;

        return $clone;
    }

    /**
     * Enable rich text mode with safe HTML tags.
     *
     * Allows common formatting tags: p, br, strong, em, a, ul, ol, li,
     * headings, blockquote, code, and pre.
     */
    public function richText(): static
    {
        return $this->allowHtml(self::SAFE_HTML_TAGS);
    }

    /**
     * Enable basic HTML mode with minimal formatting tags.
     *
     * Allows only: p, br, strong, em.
     */
    public function basicHtml(): static
    {
        return $this->allowHtml(self::BASIC_HTML_TAGS);
    }

    /**
     * Set global maximum input length.
     *
     * Inputs exceeding this length will be truncated.
     * Set to 0 for unlimited length.
     *
     * @param  int  $maxLength  Maximum length in characters (0 = unlimited)
     */
    public function maxLength(int $maxLength): static
    {
        $clone = clone $this;
        $clone->maxLength = max(0, $maxLength);

        return $clone;
    }

    /**
     * Apply an email preset to sanitise email addresses.
     *
     * Sanitises using FILTER_SANITIZE_EMAIL and lowercases the result.
     * Use for fields that should contain valid email addresses.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function email(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_EMAIL, $fields);
    }

    /**
     * Apply a URL preset to sanitise URLs.
     *
     * Sanitises using FILTER_SANITIZE_URL.
     * Use for fields that should contain valid URLs.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function url(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_URL, $fields);
    }

    /**
     * Apply a phone preset to sanitise phone numbers.
     *
     * Keeps only digits, plus signs, hyphens, parentheses, and spaces.
     * Use for fields that should contain phone numbers.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function phone(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_PHONE, $fields);
    }

    /**
     * Apply an alpha preset to allow only letters.
     *
     * Keeps only alphabetic characters (including Unicode letters).
     * Use for fields like names that should only contain letters.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function alpha(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_ALPHA, $fields);
    }

    /**
     * Apply an alphanumeric preset to allow only letters and numbers.
     *
     * Keeps only alphanumeric characters (including Unicode).
     * Use for usernames, codes, or similar fields.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function alphanumeric(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_ALPHANUMERIC, $fields);
    }

    /**
     * Apply a numeric preset to allow only numbers.
     *
     * Keeps only digits, decimal points, and minus signs.
     * Use for numeric input fields.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function numeric(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_NUMERIC, $fields);
    }

    /**
     * Apply a slug preset to create URL-safe slugs.
     *
     * Lowercases and keeps only lowercase letters, numbers, and hyphens.
     * Use for URL slugs, identifiers, or similar fields.
     *
     * @param  string  ...$fields  Field names to apply the preset to (empty = all fields)
     */
    public function slug(string ...$fields): static
    {
        return $this->applyPresetToFields(self::PRESET_SLUG, $fields);
    }

    /**
     * Register a custom preset.
     *
     * @param  string  $name  Preset name
     * @param  array{pattern?: string, filter?: int, transform?: callable(string): string}  $definition
     */
    public static function registerPreset(string $name, array $definition): void
    {
        self::$presets[$name] = $definition;
    }

    /**
     * Get all registered presets.
     *
     * @return array<string, array{pattern?: string, filter?: int, transform?: callable(string): string}>
     */
    public static function getPresets(): array
    {
        return self::$presets;
    }

    /**
     * Apply a preset to specified fields or globally.
     *
     * @param  array<string>  $fields
     */
    protected function applyPresetToFields(string $presetName, array $fields): static
    {
        $clone = clone $this;

        if (empty($fields)) {
            // Apply globally by setting a default preset
            $clone->schema['*'] = array_merge(
                $clone->schema['*'] ?? [],
                ['preset' => $presetName]
            );
        } else {
            // Apply to specific fields
            foreach ($fields as $field) {
                $clone->schema[$field] = array_merge(
                    $clone->schema[$field] ?? [],
                    ['preset' => $presetName]
                );
            }
        }

        return $clone;
    }

    // =========================================================================
    // TRANSFORMATION HOOKS
    // =========================================================================

    /**
     * Register a global before-filter transformation hook.
     *
     * The callback receives the value and field name, and should return
     * the transformed value. Multiple hooks are executed in order.
     *
     * @param  callable(string, string): string  $callback
     */
    public function beforeFilter(callable $callback): static
    {
        $clone = clone $this;
        $clone->beforeHooks[] = $callback;

        return $clone;
    }

    /**
     * Register a global after-filter transformation hook.
     *
     * The callback receives the value and field name, and should return
     * the transformed value. Multiple hooks are executed in order.
     *
     * @param  callable(string, string): string  $callback
     */
    public function afterFilter(callable $callback): static
    {
        $clone = clone $this;
        $clone->afterHooks[] = $callback;

        return $clone;
    }

    /**
     * Register a field-specific transformation hook.
     *
     * The callback receives only the value (field name is known).
     * Use `$stage` to control when the hook runs:
     * - 'before': Run before standard filtering
     * - 'after': Run after standard filtering (default)
     *
     * @param  string  $field  Field name to transform
     * @param  callable(string): string  $callback
     * @param  string  $stage  When to run: 'before' or 'after'
     */
    public function transformField(string $field, callable $callback, string $stage = 'after'): static
    {
        $clone = clone $this;

        if (! isset($clone->fieldHooks[$field])) {
            $clone->fieldHooks[$field] = ['before' => [], 'after' => []];
        }

        $stage = in_array($stage, ['before', 'after'], true) ? $stage : 'after';
        $clone->fieldHooks[$field][$stage][] = $callback;

        return $clone;
    }

    /**
     * Register a before-filter hook for specific fields.
     *
     * @param  callable(string): string  $callback
     * @param  string  ...$fields  Field names to apply the hook to
     */
    public function beforeFilterFields(callable $callback, string ...$fields): static
    {
        $clone = $this;

        foreach ($fields as $field) {
            $clone = $clone->transformField($field, $callback, 'before');
        }

        return $clone;
    }

    /**
     * Register an after-filter hook for specific fields.
     *
     * @param  callable(string): string  $callback
     * @param  string  ...$fields  Field names to apply the hook to
     */
    public function afterFilterFields(callable $callback, string ...$fields): static
    {
        $clone = $this;

        foreach ($fields as $field) {
            $clone = $clone->transformField($field, $callback, 'after');
        }

        return $clone;
    }

    /**
     * Apply all before hooks to a value.
     *
     * @param  string  $value  The value to transform
     * @param  string  $fieldName  The field name
     */
    protected function applyBeforeHooks(string $value, string $fieldName): string
    {
        // Apply global before hooks
        foreach ($this->beforeHooks as $hook) {
            $value = $hook($value, $fieldName);
        }

        // Apply field-specific before hooks
        if (isset($this->fieldHooks[$fieldName]['before'])) {
            foreach ($this->fieldHooks[$fieldName]['before'] as $hook) {
                $value = $hook($value);
            }
        }

        return $value;
    }

    /**
     * Apply all after hooks to a value.
     *
     * @param  string  $value  The value to transform
     * @param  string  $fieldName  The field name
     */
    protected function applyAfterHooks(string $value, string $fieldName): string
    {
        // Apply global after hooks
        foreach ($this->afterHooks as $hook) {
            $value = $hook($value, $fieldName);
        }

        // Apply field-specific after hooks
        if (isset($this->fieldHooks[$fieldName]['after'])) {
            foreach ($this->fieldHooks[$fieldName]['after'] as $hook) {
                $value = $hook($value);
            }
        }

        return $value;
    }

    /**
     * Check if any transformation hooks are registered.
     */
    public function hasTransformationHooks(): bool
    {
        return ! empty($this->beforeHooks)
            || ! empty($this->afterHooks)
            || ! empty($this->fieldHooks);
    }

    /**
     * Get the count of registered hooks.
     *
     * @return array{before: int, after: int, field: int}
     */
    public function getHookCounts(): array
    {
        $fieldCount = 0;
        foreach ($this->fieldHooks as $hooks) {
            $fieldCount += count($hooks['before'] ?? []) + count($hooks['after'] ?? []);
        }

        return [
            'before' => count($this->beforeHooks),
            'after' => count($this->afterHooks),
            'field' => $fieldCount,
        ];
    }

    /**
     * Strip dangerous control characters from all values.
     *
     * Only strips ASCII 0-31 (null bytes, control characters).
     * Preserves Unicode (UTF-8 high bytes) for international input.
     * Handles nested arrays recursively.
     * Applies per-field filter rules from schema.
     * Normalizes Unicode to NFC form (if enabled).
     */
    public function filter(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        return $this->filterRecursive($input, '');
    }

    /**
     * Recursively filter array values.
     *
     * @param  string  $path  Current path for nested arrays (for logging)
     */
    protected function filterRecursive(array $input, string $path = ''): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $currentPath = $path === '' ? (string) $key : $path.'.'.$key;

            if (is_array($value)) {
                // Recursively filter nested arrays
                $result[$key] = $this->filterRecursive($value, $currentPath);
            } elseif (is_string($value)) {
                // Apply filters to string values
                $result[$key] = $this->filterString($value, $currentPath, (string) $key);
            } else {
                // Pass through non-string values unchanged
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Apply filters to a string value.
     *
     * The filtering pipeline executes in this order:
     * 1. Before hooks (global, then field-specific)
     * 2. Unicode NFC normalization
     * 3. Control character stripping
     * 4. HTML filtering
     * 5. Preset application
     * 6. Additional schema filters
     * 7. Max length enforcement
     * 8. After hooks (global, then field-specific)
     * 9. Audit logging (if enabled and value changed)
     *
     * @param  string  $path  Full path for logging
     * @param  string  $fieldName  Top-level field name for schema lookup
     */
    protected function filterString(string $value, string $path, string $fieldName): string
    {
        $original = $value;
        $fieldSchema = $this->schema[$fieldName] ?? [];
        $globalSchema = $this->schema['*'] ?? [];

        // Merge global schema with field-specific schema (field takes precedence)
        $effectiveSchema = array_merge($globalSchema, $fieldSchema);

        // Step 0: Apply before hooks
        $value = $this->applyBeforeHooks($value, $fieldName);

        // Step 1: Unicode NFC normalization (unless skipped)
        $skipNormalize = $effectiveSchema['skip_normalize'] ?? false;
        if ($this->normalizeUnicode && ! $skipNormalize && $this->isNormalizerAvailable()) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        // Step 2: Strip control characters (unless skipped)
        $skipControlStrip = $effectiveSchema['skip_control_strip'] ?? false;
        if (! $skipControlStrip) {
            $value = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW) ?? '';
        }

        // Step 3: Handle HTML tags (strip or allow based on configuration)
        $value = $this->filterHtml($value, $effectiveSchema);

        // Step 4: Apply preset if specified
        $value = $this->applyPreset($value, $effectiveSchema);

        // Step 5: Apply additional schema-defined filters
        $additionalFilters = $effectiveSchema['filters'] ?? [];
        $additionalOptions = $effectiveSchema['options'] ?? [];

        foreach ($additionalFilters as $filter) {
            $options = 0;
            foreach ($additionalOptions as $option) {
                $options |= $option;
            }

            $filtered = $options > 0
                ? filter_var($value, $filter, $options)
                : filter_var($value, $filter);
            if ($filtered !== false) {
                $value = $filtered;
            }
        }

        // Step 6: Enforce max length
        $value = $this->enforceMaxLength($value, $effectiveSchema);

        // Step 7: Apply after hooks
        $value = $this->applyAfterHooks($value, $fieldName);

        // Step 8: Audit logging if content was modified
        if ($this->auditEnabled && $this->logger !== null && $value !== $original) {
            $this->logSanitisation($path, $original, $value);
        }

        return $value;
    }

    /**
     * Apply a preset to a value.
     *
     * @param  string  $value  The value to transform
     * @param  array  $schema  The effective schema containing preset configuration
     */
    protected function applyPreset(string $value, array $schema): string
    {
        $presetName = $schema['preset'] ?? null;

        if ($presetName === null || ! isset(self::$presets[$presetName])) {
            return $value;
        }

        $preset = self::$presets[$presetName];

        // Apply regex pattern if defined
        if (isset($preset['pattern'])) {
            $value = preg_replace($preset['pattern'], '', $value) ?? $value;
        }

        // Apply filter if defined
        if (isset($preset['filter'])) {
            $filtered = filter_var($value, $preset['filter']);
            if ($filtered !== false) {
                $value = $filtered;
            }
        }

        // Apply transform function if defined
        if (isset($preset['transform'])) {
            $transform = $preset['transform'];
            if (is_callable($transform)) {
                $value = $transform($value);
            } elseif (is_string($transform) && function_exists($transform)) {
                $value = $transform($value);
            }
        }

        return $value;
    }

    /**
     * Filter HTML from value based on configuration.
     *
     * @param  string  $value  The value to filter
     * @param  array  $effectiveSchema  Effective schema (merged global + field)
     */
    protected function filterHtml(string $value, array $effectiveSchema): string
    {
        // Check field-specific HTML allowance first
        $allowHtml = $effectiveSchema['allow_html'] ?? null;

        if ($allowHtml !== null) {
            if ($allowHtml === true) {
                // Allow default safe HTML tags
                return strip_tags($value, self::SAFE_HTML_TAGS);
            } elseif ($allowHtml === false) {
                // Strip all HTML
                return strip_tags($value);
            } elseif (is_string($allowHtml) && $allowHtml !== '') {
                // Use custom allowed tags
                return strip_tags($value, $allowHtml);
            }
        }

        // Fall back to global setting
        if ($this->allowedHtmlTags !== '') {
            return strip_tags($value, $this->allowedHtmlTags);
        }

        // No HTML filtering by default (preserves BC)
        return $value;
    }

    /**
     * Enforce maximum length on value.
     *
     * @param  string  $value  The value to truncate
     * @param  array  $effectiveSchema  Effective schema (merged global + field)
     */
    protected function enforceMaxLength(string $value, array $effectiveSchema): string
    {
        // Check field-specific max length first
        $maxLength = $effectiveSchema['max_length'] ?? null;

        if ($maxLength === null) {
            // Fall back to global setting
            $maxLength = $this->maxLength;
        }

        // 0 means unlimited
        if ($maxLength <= 0) {
            return $value;
        }

        // Truncate if needed (using mb_substr for Unicode safety)
        if (mb_strlen($value) > $maxLength) {
            return mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Log when content is modified during sanitisation.
     *
     * @param  string  $path  Field path
     * @param  string  $original  Original value
     * @param  string  $sanitised  Sanitised value
     */
    protected function logSanitisation(string $path, string $original, string $sanitised): void
    {
        // Truncate long values for logging
        $maxLength = 100;
        $originalTruncated = mb_strlen($original) > $maxLength
            ? mb_substr($original, 0, $maxLength).'...'
            : $original;
        $sanitisedTruncated = mb_strlen($sanitised) > $maxLength
            ? mb_substr($sanitised, 0, $maxLength).'...'
            : $sanitised;

        // Convert control characters to visible representation for logging
        $originalVisible = $this->makeControlCharsVisible($originalTruncated);
        $sanitisedVisible = $this->makeControlCharsVisible($sanitisedTruncated);

        $this->logger->info('Input sanitised', [
            'field' => $path,
            'original' => $originalVisible,
            'sanitised' => $sanitisedVisible,
            'original_length' => mb_strlen($original),
            'sanitised_length' => mb_strlen($sanitised),
        ]);
    }

    /**
     * Convert control characters to visible Unicode representation.
     */
    protected function makeControlCharsVisible(string $value): string
    {
        // Replace control characters (0x00-0x1F) with Unicode Control Pictures (U+2400-U+241F)
        return preg_replace_callback(
            '/[\x00-\x1F]/',
            fn ($matches) => mb_chr(0x2400 + ord($matches[0])),
            $value
        ) ?? $value;
    }

    /**
     * Check if the Normalizer class is available.
     */
    protected function isNormalizerAvailable(): bool
    {
        return class_exists(Normalizer::class);
    }
}
