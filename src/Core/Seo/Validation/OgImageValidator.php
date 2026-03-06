<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Validation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Validates Open Graph image dimensions.
 *
 * Open Graph images should meet minimum size requirements for optimal
 * display across social media platforms:
 * - Facebook: 1200 x 630 (recommended), minimum 600 x 315
 * - Twitter: 1200 x 628 (large card), minimum 120 x 120
 * - LinkedIn: 1200 x 627 (recommended)
 *
 * This validator checks images against these requirements and provides
 * warnings for suboptimal configurations.
 */
class OgImageValidator
{
    /**
     * Recommended dimensions for optimal display.
     */
    public const RECOMMENDED_WIDTH = 1200;

    public const RECOMMENDED_HEIGHT = 630;

    /**
     * Minimum acceptable dimensions.
     */
    public const MIN_WIDTH = 600;

    public const MIN_HEIGHT = 315;

    /**
     * Maximum acceptable dimensions (to prevent oversized images).
     */
    public const MAX_WIDTH = 8192;

    public const MAX_HEIGHT = 8192;

    /**
     * Recommended aspect ratio range (1.91:1 is ideal for Facebook/Twitter).
     */
    public const IDEAL_ASPECT_RATIO = 1.91;

    public const ASPECT_RATIO_TOLERANCE = 0.15;

    /**
     * Maximum file size in bytes (5MB).
     */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Supported image formats.
     *
     * @var array<string>
     */
    public const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Cache TTL for remote image validation (5 minutes).
     */
    protected const CACHE_TTL = 300;

    /**
     * Validate an OG image URL.
     *
     * @param  string  $imageUrl  The image URL to validate
     * @param  bool  $fetchRemote  Whether to fetch remote images for validation
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, dimensions: array{width: int|null, height: int|null}}
     */
    public function validate(string $imageUrl, bool $fetchRemote = true): array
    {
        $errors = [];
        $warnings = [];
        $width = null;
        $height = null;

        // Validate URL format
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid image URL format';

            return $this->result(false, $errors, $warnings, $width, $height);
        }

        // Check file extension
        $extension = $this->getExtension($imageUrl);
        if ($extension && ! in_array(strtolower($extension), self::SUPPORTED_FORMATS, true)) {
            $warnings[] = "Image format '$extension' may not be supported by all platforms. Recommended: JPG, PNG";
        }

        // Check protocol
        if (! str_starts_with($imageUrl, 'https://')) {
            $warnings[] = 'Image should be served over HTTPS for security';
        }

        // Try to get dimensions
        if ($fetchRemote) {
            $dimensions = $this->getDimensions($imageUrl);

            if ($dimensions !== null) {
                $width = $dimensions['width'];
                $height = $dimensions['height'];
                $fileSize = $dimensions['size'] ?? null;

                // Validate dimensions
                $dimensionResult = $this->validateDimensions($width, $height);
                $errors = array_merge($errors, $dimensionResult['errors']);
                $warnings = array_merge($warnings, $dimensionResult['warnings']);

                // Validate file size
                if ($fileSize !== null && $fileSize > self::MAX_FILE_SIZE) {
                    $sizeMb = round($fileSize / 1024 / 1024, 2);
                    $warnings[] = "Image file size ({$sizeMb}MB) exceeds recommended maximum of 5MB";
                }
            } else {
                $warnings[] = 'Could not fetch image to validate dimensions';
            }
        }

        return $this->result(empty($errors), $errors, $warnings, $width, $height);
    }

    /**
     * Validate image dimensions.
     *
     * @return array{errors: array<string>, warnings: array<string>}
     */
    public function validateDimensions(int $width, int $height): array
    {
        $errors = [];
        $warnings = [];

        // Check minimum dimensions
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            $errors[] = sprintf(
                'Image dimensions (%dx%d) are below minimum required (%dx%d)',
                $width,
                $height,
                self::MIN_WIDTH,
                self::MIN_HEIGHT
            );
        }

        // Check maximum dimensions
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $warnings[] = sprintf(
                'Image dimensions (%dx%d) exceed maximum recommended (%dx%d)',
                $width,
                $height,
                self::MAX_WIDTH,
                self::MAX_HEIGHT
            );
        }

        // Check if below recommended dimensions
        if ($width < self::RECOMMENDED_WIDTH || $height < self::RECOMMENDED_HEIGHT) {
            if ($width >= self::MIN_WIDTH && $height >= self::MIN_HEIGHT) {
                $warnings[] = sprintf(
                    'Image dimensions (%dx%d) are below recommended (%dx%d) for optimal display',
                    $width,
                    $height,
                    self::RECOMMENDED_WIDTH,
                    self::RECOMMENDED_HEIGHT
                );
            }
        }

        // Check aspect ratio
        if ($height > 0) {
            $aspectRatio = $width / $height;
            $deviation = abs($aspectRatio - self::IDEAL_ASPECT_RATIO);

            if ($deviation > self::ASPECT_RATIO_TOLERANCE) {
                $warnings[] = sprintf(
                    'Image aspect ratio (%.2f:1) differs from ideal (1.91:1). Consider using %dx%d',
                    $aspectRatio,
                    self::RECOMMENDED_WIDTH,
                    self::RECOMMENDED_HEIGHT
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate OG data from SEO metadata.
     *
     * @param  array<string, mixed>|null  $ogData  The og_data array from SeoMetadata
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateOgData(?array $ogData): array
    {
        if ($ogData === null || empty($ogData['image'])) {
            return $this->result(true, [], ['No OG image specified']);
        }

        $image = $ogData['image'];

        // Handle array format (image with width/height specified)
        if (is_array($image)) {
            $imageUrl = $image['url'] ?? $image[0] ?? null;
            $width = isset($image['width']) ? (int) $image['width'] : null;
            $height = isset($image['height']) ? (int) $image['height'] : null;

            if ($imageUrl === null) {
                return $this->result(false, ['OG image URL is missing'], []);
            }

            // If dimensions are provided, validate them directly
            if ($width !== null && $height !== null) {
                $dimResult = $this->validateDimensions($width, $height);

                return $this->result(
                    empty($dimResult['errors']),
                    $dimResult['errors'],
                    $dimResult['warnings'],
                    $width,
                    $height
                );
            }

            return $this->validate($imageUrl);
        }

        // Handle string format
        return $this->validate($image);
    }

    /**
     * Get image dimensions from URL (with caching).
     *
     * @return array{width: int, height: int, size: int|null}|null
     */
    protected function getDimensions(string $imageUrl): ?array
    {
        $cacheKey = 'og_image_dims:'.md5($imageUrl);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($imageUrl) {
            return $this->fetchDimensions($imageUrl);
        });
    }

    /**
     * Fetch image dimensions from URL.
     *
     * @return array{width: int, height: int, size: int|null}|null
     */
    protected function fetchDimensions(string $imageUrl): ?array
    {
        try {
            // Try to get dimensions from local storage first
            if ($this->isLocalUrl($imageUrl)) {
                return $this->getLocalDimensions($imageUrl);
            }

            // For remote URLs, use getimagesize with stream context
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Core-PHP-SEO-Validator/1.0',
                ],
            ]);

            $imageInfo = @getimagesize($imageUrl, $info);

            if ($imageInfo === false) {
                return null;
            }

            // Try to get file size via HEAD request
            $fileSize = $this->getRemoteFileSize($imageUrl);

            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'size' => $fileSize,
            ];
        } catch (\Exception $e) {
            Log::debug('Failed to fetch OG image dimensions', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if URL is a local storage URL.
     */
    protected function isLocalUrl(string $url): bool
    {
        $appUrl = config('app.url', '');

        return str_starts_with($url, $appUrl) ||
               str_starts_with($url, '/storage/') ||
               str_starts_with($url, 'storage/');
    }

    /**
     * Get dimensions for a local file.
     *
     * @return array{width: int, height: int, size: int|null}|null
     */
    protected function getLocalDimensions(string $url): ?array
    {
        // Convert URL to local path
        $path = $this->urlToLocalPath($url);

        if ($path === null || ! file_exists($path)) {
            return null;
        }

        $imageInfo = @getimagesize($path);

        if ($imageInfo === false) {
            return null;
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'size' => filesize($path) ?: null,
        ];
    }

    /**
     * Convert URL to local file path.
     */
    protected function urlToLocalPath(string $url): ?string
    {
        $appUrl = config('app.url', '');

        // Remove app URL prefix
        if (str_starts_with($url, $appUrl)) {
            $url = substr($url, strlen($appUrl));
        }

        // Handle /storage/ prefix
        if (str_starts_with($url, '/storage/')) {
            $relativePath = substr($url, 9);

            return Storage::disk('public')->path($relativePath);
        }

        return public_path(ltrim($url, '/'));
    }

    /**
     * Get remote file size via HEAD request.
     */
    protected function getRemoteFileSize(string $url): ?int
    {
        try {
            $response = Http::timeout(3)->head($url);

            if ($response->successful()) {
                $contentLength = $response->header('Content-Length');

                return $contentLength !== null ? (int) $contentLength : null;
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Get file extension from URL.
     */
    protected function getExtension(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === null || $path === false) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? $extension : null;
    }

    /**
     * Build validation result array.
     *
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, dimensions: array{width: int|null, height: int|null}}
     */
    protected function result(bool $valid, array $errors, array $warnings, ?int $width = null, ?int $height = null): array
    {
        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'dimensions' => [
                'width' => $width,
                'height' => $height,
            ],
        ];
    }
}
