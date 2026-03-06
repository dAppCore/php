<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Mail;

/**
 * Email Validation Result Value Object
 *
 * Represents the result of email validation by EmailShield service.
 */
class EmailValidationResult
{
    /**
     * Create a new email validation result.
     *
     * @param  bool  $isValid  Whether the email is valid
     * @param  bool  $isDisposable  Whether the email is from a disposable domain
     * @param  string|null  $domain  The email domain
     * @param  string|null  $reason  The reason for validation failure
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly bool $isDisposable,
        public readonly ?string $domain = null,
        public readonly ?string $reason = null
    ) {}

    /**
     * Create a valid email result.
     */
    public static function valid(string $domain): self
    {
        return new self(
            isValid: true,
            isDisposable: false,
            domain: $domain,
            reason: null
        );
    }

    /**
     * Create an invalid email result.
     */
    public static function invalid(string $reason, ?string $domain = null): self
    {
        return new self(
            isValid: false,
            isDisposable: false,
            domain: $domain,
            reason: $reason
        );
    }

    /**
     * Create a disposable email result.
     */
    public static function disposable(string $domain): self
    {
        return new self(
            isValid: false,
            isDisposable: true,
            domain: $domain,
            reason: 'Disposable email addresses are not allowed'
        );
    }

    /**
     * Check if the email validation passed.
     */
    public function passes(): bool
    {
        return $this->isValid && ! $this->isDisposable;
    }

    /**
     * Check if the email validation failed.
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Get the validation message.
     */
    public function getMessage(): string
    {
        if ($this->passes()) {
            return 'Valid email address';
        }

        return $this->reason ?? 'Invalid email address';
    }

    /**
     * Convert the result to an array for caching.
     *
     * @return array{is_valid: bool, is_disposable: bool, domain: string|null, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'is_disposable' => $this->isDisposable,
            'domain' => $this->domain,
            'reason' => $this->reason,
        ];
    }

    /**
     * Create a result from a cached array.
     *
     * @param  array{is_valid: bool, is_disposable: bool, domain: string|null, reason: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isValid: $data['is_valid'],
            isDisposable: $data['is_disposable'],
            domain: $data['domain'] ?? null,
            reason: $data['reason'] ?? null
        );
    }
}
