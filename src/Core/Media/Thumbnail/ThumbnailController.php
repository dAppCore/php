<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Thumbnail;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for serving lazy-generated thumbnails.
 *
 * Handles requests to the `/media/thumb` endpoint, generating thumbnails
 * on-demand when first requested and serving them with appropriate caching headers.
 *
 * ## Security
 *
 * All requests are validated against a signed URL signature to prevent
 * unauthorized thumbnail generation or path traversal attacks.
 *
 * ## Caching
 *
 * Generated thumbnails are cached on disk and served with browser cache headers.
 * Configure cache duration via `images.lazy_thumbnails.browser_cache_ttl`.
 */
class ThumbnailController extends Controller
{
    /**
     * Maximum allowed thumbnail width.
     */
    protected const MAX_WIDTH = 2000;

    /**
     * Maximum allowed thumbnail height.
     */
    protected const MAX_HEIGHT = 2000;

    /**
     * Minimum allowed thumbnail dimension.
     */
    protected const MIN_DIMENSION = 10;

    /**
     * Serve a lazy-generated thumbnail.
     *
     * @return Response|BinaryFileResponse|StreamedResponse
     */
    public function show(Request $request, LazyThumbnail $lazyThumbnail)
    {
        // Validate required parameters
        if (! $request->has(['path', 'w', 'h', 'sig'])) {
            return $this->notFound();
        }

        // Decode and validate path
        $encodedPath = $request->input('path');
        $sourcePath = base64_decode($encodedPath, true);

        if ($sourcePath === false || $sourcePath === '') {
            Log::warning('ThumbnailController: Invalid path encoding', [
                'encoded' => $encodedPath,
            ]);

            return $this->notFound();
        }

        // Prevent path traversal
        if ($this->hasPathTraversal($sourcePath)) {
            Log::warning('ThumbnailController: Path traversal attempt blocked', [
                'path' => $sourcePath,
            ]);

            return $this->forbidden();
        }

        // Parse and validate dimensions
        $width = (int) $request->input('w');
        $height = (int) $request->input('h');

        if (! $this->validateDimensions($width, $height)) {
            Log::warning('ThumbnailController: Invalid dimensions', [
                'width' => $width,
                'height' => $height,
            ]);

            return $this->badRequest('Invalid dimensions');
        }

        // Verify signature
        $signature = $request->input('sig');
        if (! $lazyThumbnail->verifySignature($sourcePath, $width, $height, $signature)) {
            Log::warning('ThumbnailController: Invalid signature', [
                'path' => $sourcePath,
            ]);

            return $this->forbidden();
        }

        // Check if lazy thumbnails are enabled
        if (! config('images.lazy_thumbnails.enabled', true)) {
            return $this->serviceUnavailable();
        }

        // Check if source can be processed
        if (! $lazyThumbnail->canGenerate($sourcePath)) {
            Log::debug('ThumbnailController: Cannot generate thumbnail for source', [
                'path' => $sourcePath,
            ]);

            return $this->returnPlaceholder($lazyThumbnail, $width, $height);
        }

        // Get or generate thumbnail
        $thumbnailPath = $lazyThumbnail->get($sourcePath, $width, $height);

        if ($thumbnailPath === null) {
            // Thumbnail is being generated asynchronously
            return $this->returnPlaceholder($lazyThumbnail, $width, $height);
        }

        // Serve the thumbnail
        return $this->serveThumbnail($thumbnailPath, $lazyThumbnail);
    }

    /**
     * Serve an existing thumbnail file.
     *
     * @return StreamedResponse
     */
    protected function serveThumbnail(string $thumbnailPath, LazyThumbnail $lazyThumbnail)
    {
        $disk = Storage::disk(config('images.lazy_thumbnails.thumbnail_disk', 'public'));

        if (! $disk->exists($thumbnailPath)) {
            return $this->notFound();
        }

        $mimeType = $disk->mimeType($thumbnailPath) ?? 'image/jpeg';
        $lastModified = $disk->lastModified($thumbnailPath);
        $etag = md5($thumbnailPath.$lastModified);

        // Check for conditional request
        $ifNoneMatch = request()->header('If-None-Match');
        if ($ifNoneMatch === "\"{$etag}\"") {
            return response('', 304);
        }

        $cacheTtl = config('images.lazy_thumbnails.browser_cache_ttl', 604800); // 7 days

        return response()->stream(
            function () use ($disk, $thumbnailPath) {
                echo $disk->get($thumbnailPath);
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $disk->size($thumbnailPath),
                'Cache-Control' => "public, max-age={$cacheTtl}",
                'ETag' => "\"{$etag}\"",
                'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            ]
        );
    }

    /**
     * Return a placeholder image response.
     *
     * @return Response|StreamedResponse
     */
    protected function returnPlaceholder(LazyThumbnail $lazyThumbnail, int $width, int $height)
    {
        $placeholder = $lazyThumbnail->getPlaceholder($width, $height);

        // If placeholder is a URL, redirect to it
        if ($placeholder !== null && str_starts_with($placeholder, 'http')) {
            return response('', 307, [
                'Location' => $placeholder,
                'Cache-Control' => 'no-cache',
            ]);
        }

        // Generate a simple SVG placeholder
        $svg = $this->generateSvgPlaceholder($width, $height);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Generate a simple SVG placeholder.
     */
    protected function generateSvgPlaceholder(int $width, int $height): string
    {
        $color = config('images.lazy_thumbnails.placeholder_color', '#e5e7eb');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
    <rect width="100%" height="100%" fill="{$color}"/>
</svg>
SVG;
    }

    /**
     * Validate thumbnail dimensions.
     */
    protected function validateDimensions(int $width, int $height): bool
    {
        $minDimension = config('images.lazy_thumbnails.min_dimension', self::MIN_DIMENSION);
        $maxWidth = config('images.lazy_thumbnails.max_width', self::MAX_WIDTH);
        $maxHeight = config('images.lazy_thumbnails.max_height', self::MAX_HEIGHT);

        return $width >= $minDimension
            && $height >= $minDimension
            && $width <= $maxWidth
            && $height <= $maxHeight;
    }

    /**
     * Check if path contains traversal attempts.
     */
    protected function hasPathTraversal(string $path): bool
    {
        // Block parent directory references
        if (str_contains($path, '..')) {
            return true;
        }

        // Block absolute paths
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            return true;
        }

        // Block null bytes
        if (str_contains($path, "\0")) {
            return true;
        }

        return false;
    }

    /**
     * Return a 404 Not Found response.
     */
    protected function notFound(): Response
    {
        return response('Not Found', 404);
    }

    /**
     * Return a 403 Forbidden response.
     */
    protected function forbidden(): Response
    {
        return response('Forbidden', 403);
    }

    /**
     * Return a 400 Bad Request response.
     */
    protected function badRequest(string $message = 'Bad Request'): Response
    {
        return response($message, 400);
    }

    /**
     * Return a 503 Service Unavailable response.
     */
    protected function serviceUnavailable(): Response
    {
        return response('Service Unavailable', 503);
    }
}
