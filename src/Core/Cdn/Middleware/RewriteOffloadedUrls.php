<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Middleware;

use Closure;
use Core\Cdn\Services\StorageOffload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to rewrite local storage URLs to offloaded remote URLs.
 *
 * Processes JSON responses and replaces local storage paths with
 * their remote equivalents if the file has been offloaded.
 */
class RewriteOffloadedUrls
{
    protected StorageOffload $offloadService;

    public function __construct(StorageOffload $offloadService)
    {
        $this->offloadService = $offloadService;
    }

    /**
     * Handle an incoming request.
     *
     * Rewrites URLs in JSON responses to point to offloaded storage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process JSON responses
        if (! $this->shouldProcess($response)) {
            return $response;
        }

        // Get response content
        $content = $response->getContent();
        if (empty($content)) {
            return $response;
        }

        // Decode JSON
        $data = json_decode($content, true);
        if ($data === null) {
            return $response;
        }

        // Rewrite URLs in the data
        $rewritten = $this->rewriteUrls($data);

        // Update response
        $response->setContent(json_encode($rewritten));

        return $response;
    }

    /**
     * Check if response should be processed.
     */
    protected function shouldProcess(Response $response): bool
    {
        // Only process successful responses
        if (! $response->isSuccessful()) {
            return false;
        }

        // Check content type
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json');
    }

    /**
     * Recursively rewrite URLs in data structure.
     */
    protected function rewriteUrls(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->rewriteUrls($value);
            }

            return $data;
        }

        if (is_string($data)) {
            return $this->rewriteUrl($data);
        }

        return $data;
    }

    /**
     * Rewrite a single URL if it matches a local storage path.
     */
    protected function rewriteUrl(string $value): string
    {
        // Only process strings that look like URLs or paths
        if (! $this->looksLikeStoragePath($value)) {
            return $value;
        }

        // Extract local path from URL
        $localPath = $this->extractLocalPath($value);
        if (! $localPath) {
            return $value;
        }

        // Check if this path has been offloaded
        $offloadedUrl = $this->offloadService->url($localPath);
        if ($offloadedUrl) {
            return $offloadedUrl;
        }

        return $value;
    }

    /**
     * Check if a string looks like a storage path.
     */
    protected function looksLikeStoragePath(string $value): bool
    {
        // Check for /storage/ in the path
        if (str_contains($value, '/storage/')) {
            return true;
        }

        // Check for storage_path pattern
        if (preg_match('#/app/(public|private)/#', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Extract local file path from URL.
     */
    protected function extractLocalPath(string $url): ?string
    {
        // Handle /storage/ URLs (symlinked public storage)
        if (str_contains($url, '/storage/')) {
            $parts = explode('/storage/', $url, 2);
            if (count($parts) === 2) {
                return storage_path('app/public/'.$parts[1]);
            }
        }

        // Handle absolute paths
        if (str_starts_with($url, storage_path())) {
            return $url;
        }

        return null;
    }
}
