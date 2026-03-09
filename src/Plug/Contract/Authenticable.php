<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for authentication operations.
 *
 * Covers OAuth2, API key, webhook, and credential-based auth flows.
 */
interface Authenticable
{
    /**
     * Get provider identifier (e.g., 'twitter', 'bluesky').
     */
    public static function identifier(): string;

    /**
     * Get display name (e.g., 'X', 'Bluesky').
     */
    public static function name(): string;

    /**
     * Get OAuth authorisation URL.
     *
     * Returns empty string if not OAuth-based.
     */
    public function getAuthUrl(): string;

    /**
     * Exchange credentials/callback params for access token.
     *
     * @param  array  $params  OAuth callback params or credentials
     * @return array Token data or error
     */
    public function requestAccessToken(array $params): array;

    /**
     * Get authenticated account information.
     */
    public function getAccount(): Response;
}
