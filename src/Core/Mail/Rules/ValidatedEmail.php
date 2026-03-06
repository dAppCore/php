<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Mail\Rules;

use Closure;
use Core\Mail\EmailShield;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates email addresses using EmailShield service.
 *
 * This rule validates the email format and optionally blocks disposable email domains.
 *
 * Example usage:
 * ```php
 * 'email' => ['required', new ValidatedEmail(blockDisposable: true)]
 * ```
 */
class ValidatedEmail implements ValidationRule
{
    /**
     * Create a new validated email rule.
     *
     * @param  bool  $blockDisposable  Whether to block disposable email domains
     */
    public function __construct(
        public bool $blockDisposable = true
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
        if (! is_string($value)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        $emailShield = app(EmailShield::class);
        $result = $emailShield->validate($value);

        // If blocking disposable emails and this is disposable
        if ($this->blockDisposable && $result->isDisposable) {
            $fail($result->getMessage());

            return;
        }

        // If email is invalid (and not just disposable)
        if (! $result->isValid && ! $result->isDisposable) {
            $fail($result->getMessage());

            return;
        }
    }
}
