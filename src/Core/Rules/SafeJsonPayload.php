<?php

declare(strict_types=1);

namespace Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a JSON payload is safe for storage.
 *
 * Protects against:
 * - Excessively large payloads (DoS via storage bloat)
 * - Deeply nested structures (parsing/memory issues)
 * - Too many keys (storage/indexing issues)
 * - Overly long string values
 *
 * Use this for metadata fields, custom parameters, or any
 * user-provided JSON that gets stored in the database.
 */
class SafeJsonPayload implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  int  $maxSizeBytes  Maximum total size in bytes
     * @param  int  $maxDepth  Maximum nesting depth
     * @param  int  $maxKeys  Maximum total number of keys (across all levels)
     * @param  int  $maxStringLength  Maximum length of any string value
     */
    public function __construct(
        protected int $maxSizeBytes = 10240, // 10KB default
        protected int $maxDepth = 3,
        protected int $maxKeys = 50,
        protected int $maxStringLength = 1000
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The :attribute must be a valid JSON object or array.');

            return;
        }

        // Check total encoded size
        $encoded = json_encode($value);
        if ($encoded === false || strlen($encoded) > $this->maxSizeBytes) {
            $fail("The :attribute exceeds the maximum allowed size of {$this->maxSizeBytes} bytes.");

            return;
        }

        // Check structure
        $keyCount = 0;
        $depthError = false;
        $stringError = false;

        $this->traverseArray($value, 1, $keyCount, $depthError, $stringError);

        if ($depthError) {
            $fail("The :attribute exceeds the maximum nesting depth of {$this->maxDepth} levels.");

            return;
        }

        if ($keyCount > $this->maxKeys) {
            $fail("The :attribute exceeds the maximum of {$this->maxKeys} keys.");

            return;
        }

        if ($stringError) {
            $fail("The :attribute contains string values exceeding {$this->maxStringLength} characters.");

            return;
        }
    }

    /**
     * Recursively traverse array to check depth, key count, and string lengths.
     */
    protected function traverseArray(array $array, int $currentDepth, int &$keyCount, bool &$depthError, bool &$stringError): void
    {
        if ($currentDepth > $this->maxDepth) {
            $depthError = true;

            return;
        }

        foreach ($array as $key => $value) {
            $keyCount++;

            if ($keyCount > $this->maxKeys) {
                return;
            }

            if (is_string($value) && strlen($value) > $this->maxStringLength) {
                $stringError = true;

                return;
            }

            if (is_array($value)) {
                $this->traverseArray($value, $currentDepth + 1, $keyCount, $depthError, $stringError);

                if ($depthError || $stringError || $keyCount > $this->maxKeys) {
                    return;
                }
            }
        }
    }

    /**
     * Create with default limits (10KB, 3 depth, 50 keys, 1000 char strings).
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create with small payload limits (2KB, 2 depth, 20 keys, 500 char strings).
     */
    public static function small(): self
    {
        return new self(2048, 2, 20, 500);
    }

    /**
     * Create with large payload limits (100KB, 5 depth, 200 keys, 5000 char strings).
     */
    public static function large(): self
    {
        return new self(102400, 5, 200, 5000);
    }

    /**
     * Create for metadata/tags (5KB, 2 depth, 30 keys, 256 char strings).
     */
    public static function metadata(): self
    {
        return new self(5120, 2, 30, 256);
    }
}
