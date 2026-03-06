<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a valid hexadecimal colour code.
 *
 * Supports both 3-digit (#fff) and 6-digit (#ffffff) hex codes.
 * The hash symbol (#) is required.
 *
 * Example valid values:
 * - #fff
 * - #FFF
 * - #ffffff
 * - #FFFFFF
 * - #a1b2c3
 */
class HexRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  bool  $forceFull  If true, only 6-digit codes are valid (3-digit codes rejected)
     */
    public function __construct(
        protected bool $forceFull = false
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The attribute being validated
     * @param  mixed  $value  The value being validated
     * @param  Closure  $fail  Closure to call if validation fails
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = '/^#([a-fA-F0-9]{6}';

        if (! $this->forceFull) {
            $pattern .= '|[a-fA-F0-9]{3}';
        }

        $pattern .= ')$/';

        if (! preg_match($pattern, $value)) {
            $fail('The :attribute must be a valid hexadecimal colour code.');
        }
    }
}
