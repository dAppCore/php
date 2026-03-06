<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Controllers;

use Core\Front\Controller;
use Core\Seo\Services\ServiceOgImageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * OG Image Controller
 *
 * Serves dynamically generated Open Graph images for service pages.
 * Images are cached to disk and served with long cache headers.
 */
class OgImageController extends Controller
{
    /**
     * Cache TTL in seconds (30 days).
     */
    private const CACHE_TTL = 2592000;

    /**
     * Serve an OG image for a service page.
     *
     * Generates the image on first request, then serves from cache.
     * Returns 404 for invalid service names.
     */
    public function service(string $service, ServiceOgImageService $ogService): Response
    {
        // Remove .png extension if present
        $service = preg_replace('/\.png$/i', '', $service);
        $service = strtolower($service);

        // Validate service name
        if (! $ogService->isValidService($service)) {
            return response('Not Found', 404);
        }

        // Generate if doesn't exist
        if (! $ogService->exists($service)) {
            $ogService->generate($service);
        }

        // Get the image content
        $filename = "og-images/services/{$service}.png";

        if (! Storage::disk('public')->exists($filename)) {
            return response('Not Found', 404);
        }

        $content = Storage::disk('public')->get($filename);
        $lastModified = Storage::disk('public')->lastModified($filename);

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($content),
            'Cache-Control' => 'public, max-age='.self::CACHE_TTL.', immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'ETag' => '"'.md5($content).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
