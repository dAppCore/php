<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers;

use Illuminate\Support\Facades\Vite;

/**
 * Service for generating and managing CSP nonces.
 *
 * CSP nonces provide a secure way to allow inline scripts and styles
 * without using 'unsafe-inline'. Each request generates a unique nonce
 * that must be included in both the CSP header and the inline element.
 *
 * ## Usage
 *
 * In Blade templates:
 * ```blade
 * <script nonce="{{ csp_nonce() }}">
 *     // Your inline JavaScript
 * </script>
 *
 * <style nonce="{{ csp_nonce() }}">
 *     // Your inline CSS
 * </style>
 * ```
 *
 * Or using the directive:
 * ```blade
 * <script @cspnonce>
 *     // Your inline JavaScript
 * </script>
 * ```
 *
 * ## Security
 *
 * - Nonces are generated once per request and cached
 * - Uses cryptographically secure random bytes
 * - Base64-encoded for safe use in HTML attributes
 * - Nonces are 128 bits (16 bytes) by default
 */
class CspNonceService
{
    /**
     * The generated nonce for this request.
     */
    protected ?string $nonce = null;

    /**
     * Whether nonce-based CSP is enabled.
     */
    protected bool $enabled = true;

    /**
     * Nonce length in bytes (before base64 encoding).
     */
    protected int $nonceLength = 16;

    public function __construct()
    {
        $this->enabled = (bool) config('headers.csp.nonce_enabled', true);
        $this->nonceLength = (int) config('headers.csp.nonce_length', 16);
    }

    /**
     * Get the CSP nonce for the current request.
     *
     * Generates a new nonce if one hasn't been created yet.
     */
    public function getNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = $this->generateNonce();
        }

        return $this->nonce;
    }

    /**
     * Generate a cryptographically secure nonce.
     *
     * Also registers it with Vite so Livewire and Vite-generated tags
     * automatically include the nonce attribute.
     */
    protected function generateNonce(): string
    {
        $nonce = base64_encode(random_bytes($this->nonceLength));

        Vite::useCspNonce($nonce);

        return $nonce;
    }

    /**
     * Get the nonce formatted for a CSP directive.
     *
     * Returns the nonce in the format: 'nonce-{base64-value}'
     */
    public function getCspNonceDirective(): string
    {
        return "'nonce-{$this->getNonce()}'";
    }

    /**
     * Get the nonce as an HTML attribute.
     *
     * Returns: nonce="{base64-value}"
     */
    public function getNonceAttribute(): string
    {
        return 'nonce="'.$this->getNonce().'"';
    }

    /**
     * Check if nonce-based CSP is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable nonce-based CSP.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable nonce-based CSP.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Reset the nonce (for testing or special cases).
     *
     * This should rarely be needed in production.
     */
    public function reset(): self
    {
        $this->nonce = null;

        return $this;
    }

    /**
     * Set a specific nonce (for testing purposes only).
     */
    public function setNonce(string $nonce): self
    {
        $this->nonce = $nonce;

        return $this;
    }
}
