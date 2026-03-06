<?php

declare(strict_types=1);

namespace Core\Plug\Concern;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Token management helpers for Plug operations.
 */
trait ManagesTokens
{
    protected array $token = [];

    /**
     * Set the access token to use for API requests.
     */
    public function withToken(array $token): static
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get the current access token.
     *
     * @throws Exception If no token is configured
     */
    public function getToken(): array
    {
        if (empty($this->token)) {
            throw new Exception('No access token configured.');
        }

        return $this->token;
    }

    /**
     * Get the access token string.
     */
    protected function accessToken(): string
    {
        return Arr::get($this->getToken(), 'access_token', '');
    }

    /**
     * Check if a refresh token is available.
     */
    public function hasRefreshToken(): bool
    {
        return ! empty(Arr::get($this->token, 'refresh_token'));
    }

    /**
     * Get the refresh token if available.
     */
    protected function refreshToken(): ?string
    {
        return Arr::get($this->token, 'refresh_token');
    }

    /**
     * Check if the token expires soon.
     *
     * @param  int  $minutes  Buffer time in minutes (default 12)
     */
    public function tokenExpiresSoon(int $minutes = 12): bool
    {
        $expiresIn = $this->token['expires_in'] ?? null;

        if (! $expiresIn) {
            return false;
        }

        $expiresAt = Carbon::createFromTimestamp($expiresIn, 'UTC');

        return $expiresAt->lte(Carbon::now('UTC')->addMinutes($minutes));
    }

    /**
     * Check if the token has expired.
     */
    public function tokenExpired(): bool
    {
        $expiresIn = $this->token['expires_in'] ?? null;

        if (! $expiresIn) {
            return false;
        }

        $expiresAt = Carbon::createFromTimestamp($expiresIn, 'UTC');

        return $expiresAt->lte(Carbon::now('UTC'));
    }
}
