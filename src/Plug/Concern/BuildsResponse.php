<?php

declare(strict_types=1);

namespace Core\Plug\Concern;

use Closure;
use Core\Plug\Enum\Status;
use Core\Plug\Response;
use Illuminate\Http\Client\Response as HttpResponse;

/**
 * Response building helpers for Plug operations.
 */
trait BuildsResponse
{
    /**
     * Create a response with the given status.
     */
    protected function response(
        Status $status,
        array $context = [],
        bool $rateLimitApproaching = false,
        int $retryAfter = 0
    ): Response {
        return new Response($status, $context, $rateLimitApproaching, $retryAfter);
    }

    /**
     * Create a success response.
     */
    protected function ok(array $context = []): Response
    {
        return $this->response(Status::OK, $context);
    }

    /**
     * Create an error response.
     */
    protected function error(string $message, array $context = []): Response
    {
        return $this->response(Status::ERROR, array_merge(['message' => $message], $context));
    }

    /**
     * Create an unauthorised response.
     */
    protected function unauthorized(string $message = 'Unauthorised'): Response
    {
        return $this->response(Status::UNAUTHORIZED, ['message' => $message]);
    }

    /**
     * Create a rate limit response.
     */
    protected function rateLimit(int $retryAfter, string $message = 'Rate limit exceeded'): Response
    {
        return $this->response(Status::RATE_LIMITED, ['message' => $message], true, $retryAfter);
    }

    /**
     * Create a no content response.
     */
    protected function noContent(): Response
    {
        return $this->response(Status::NO_CONTENT);
    }

    /**
     * Build a response from an HTTP client response.
     *
     * @param  HttpResponse  $response  The HTTP response
     * @param  Closure|null  $transform  Optional transformer for success data
     */
    protected function fromHttp(HttpResponse $response, ?Closure $transform = null): Response
    {
        if ($response->successful()) {
            $data = $response->json() ?? [];

            return $this->ok($transform ? $transform($data, $response) : $data);
        }

        if ($response->status() === 401) {
            return $this->unauthorized('Access token expired or invalid');
        }

        if ($response->status() === 429) {
            return $this->rateLimit((int) $response->header('Retry-After', 60));
        }

        $error = $response->json('error.message')
            ?? $response->json('error')
            ?? $response->json('message')
            ?? 'Unknown error';

        return $this->error(is_array($error) ? json_encode($error) : $error, [
            'status_code' => $response->status(),
            'body' => $response->json(),
        ]);
    }
}
