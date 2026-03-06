<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Headers\CspNonceService;

if (! function_exists('csp_nonce')) {
    /**
     * Get the CSP nonce for the current request.
     *
     * Usage in Blade templates:
     * ```blade
     * <script nonce="{{ csp_nonce() }}">
     *     // Your inline JavaScript
     * </script>
     * ```
     *
     * @return string The base64-encoded nonce value
     */
    function csp_nonce(): string
    {
        return app(CspNonceService::class)->getNonce();
    }
}

if (! function_exists('csp_nonce_attribute')) {
    /**
     * Get the CSP nonce as an HTML attribute.
     *
     * Usage in Blade templates:
     * ```blade
     * <script {!! csp_nonce_attribute() !!}>
     *     // Your inline JavaScript
     * </script>
     * ```
     *
     * @return string The nonce attribute (e.g., 'nonce="abc123..."')
     */
    function csp_nonce_attribute(): string
    {
        return app(CspNonceService::class)->getNonceAttribute();
    }
}
