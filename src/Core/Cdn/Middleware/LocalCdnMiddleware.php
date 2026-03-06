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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local CDN Middleware.
 *
 * When requests hit the cdn.* subdomain (e.g., cdn.core.test), this middleware
 * adds aggressive caching headers and enables compression. This provides
 * CDN-like behaviour without external services.
 *
 * With Valet wildcard:
 *   core.test -> normal app
 *   cdn.core.test -> same app, but with CDN headers
 */
class LocalCdnMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a CDN subdomain request
        if (! $this->isCdnSubdomain($request)) {
            return $next($request);
        }

        // Process the request
        $response = $next($request);

        // Add CDN headers to the response
        $this->addCdnHeaders($response, $request);

        return $response;
    }

    /**
     * Check if request is to the CDN subdomain.
     */
    protected function isCdnSubdomain(Request $request): bool
    {
        $host = $request->getHost();
        $cdnSubdomain = config('core.cdn.subdomain', 'cdn');
        $baseDomain = config('core.domain.base', 'core.test');

        // Check for cdn.{domain} pattern
        return str_starts_with($host, "{$cdnSubdomain}.");
    }

    /**
     * Add CDN-appropriate headers to the response.
     */
    protected function addCdnHeaders(Response $response, Request $request): void
    {
        // Skip if response isn't successful
        if (! $response->isSuccessful()) {
            return;
        }

        // Get cache settings from config
        $maxAge = config('core.cdn.cache_max_age', 31536000); // 1 year
        $immutable = config('core.cdn.cache_immutable', true);

        // Build Cache-Control header
        $cacheControl = "public, max-age={$maxAge}";
        if ($immutable) {
            $cacheControl .= ', immutable';
        }

        $response->headers->set('Cache-Control', $cacheControl);

        // Add ETag if possible
        if ($response instanceof BinaryFileResponse) {
            $file = $response->getFile();
            if ($file && $file->isFile()) {
                $etag = md5($file->getMTime().$file->getSize());
                $response->headers->set('ETag', "\"{$etag}\"");
            }
        }

        // Vary on Accept-Encoding for compressed responses
        $response->headers->set('Vary', 'Accept-Encoding');

        // Add timing header for debugging
        $response->headers->set('X-CDN-Cache', 'local');

        // Set Content-Type headers for common static files
        $this->setContentTypeHeaders($response, $request);
    }

    /**
     * Set appropriate Content-Type for static assets.
     */
    protected function setContentTypeHeaders(Response $response, Request $request): void
    {
        $path = $request->getPathInfo();
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'js' => 'application/javascript; charset=utf-8',
            'mjs' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'xml' => 'application/xml; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'map' => 'application/json; charset=utf-8',
        ];

        if (isset($mimeTypes[$extension])) {
            $response->headers->set('Content-Type', $mimeTypes[$extension]);
        }
    }
}
