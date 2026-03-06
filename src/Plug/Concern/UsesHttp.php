<?php

declare(strict_types=1);

namespace Core\Plug\Concern;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client helpers for Plug operations.
 */
trait UsesHttp
{
    /**
     * Get a configured HTTP client.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withHeaders($this->defaultHeaders());
    }

    /**
     * Get default headers for requests.
     *
     * Override in concrete classes to add provider-specific headers.
     */
    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => 'HostUK-Plug/1.0',
        ];
    }

    /**
     * Build a URL with query parameters.
     */
    protected function buildUrl(string $base, array $params): string
    {
        return $base.'?'.http_build_query($params, '', '&');
    }
}
