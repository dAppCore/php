<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Bunny\Storage\Client;
use Core\Config\ConfigService;
use Core\Crypt\LthnHash;
use Core\Service\Contracts\HealthCheckable;
use Core\Service\HealthCheckResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * BunnyCDN Storage Zone service for direct file operations.
 *
 * Manages file uploads/downloads to BunnyCDN storage zones:
 * - Public zone: General assets, media
 * - Private zone: DRM/gated content
 *
 * Supports vBucket scoping for workspace-isolated CDN paths.
 * Implements HealthCheckable for monitoring CDN connectivity.
 */
class BunnyStorageService implements HealthCheckable
{
    protected ?Client $publicClient = null;

    protected ?Client $privateClient = null;

    /**
     * Default maximum file size in bytes (100MB).
     */
    protected const DEFAULT_MAX_FILE_SIZE = 104857600;

    /**
     * Maximum retry attempts for failed uploads.
     */
    protected const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Base delay in milliseconds for exponential backoff.
     */
    protected const RETRY_BASE_DELAY_MS = 100;

    /**
     * Common MIME type mappings by file extension.
     *
     * @var array<string, string>
     */
    protected const MIME_TYPES = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'avif' => 'image/avif',
        'heic' => 'image/heic',
        'heif' => 'image/heif',

        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // Text/Code
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'csv' => 'text/csv',
        'md' => 'text/markdown',

        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',

        // Video
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'm4v' => 'video/mp4',

        // Archives
        'zip' => 'application/zip',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',

        // Fonts
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',

        // Other
        'wasm' => 'application/wasm',
        'map' => 'application/json',
    ];

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * Get the public storage zone client.
     */
    public function publicClient(): ?Client
    {
        if ($this->publicClient === null && $this->isConfigured('public')) {
            $this->publicClient = new Client(
                $this->config->get('cdn.bunny.storage.public.api_key'),
                $this->config->get('cdn.bunny.storage.public.name'),
                $this->config->get('cdn.bunny.storage.public.region', Client::STORAGE_ZONE_FS_EU)
            );
        }

        return $this->publicClient;
    }

    /**
     * Get the private storage zone client.
     */
    public function privateClient(): ?Client
    {
        if ($this->privateClient === null && $this->isConfigured('private')) {
            $this->privateClient = new Client(
                $this->config->get('cdn.bunny.storage.private.api_key'),
                $this->config->get('cdn.bunny.storage.private.name'),
                $this->config->get('cdn.bunny.storage.private.region', Client::STORAGE_ZONE_FS_EU)
            );
        }

        return $this->privateClient;
    }

    /**
     * Check if a storage zone is configured.
     */
    public function isConfigured(string $zone = 'public'): bool
    {
        return $this->config->isConfigured("cdn.bunny.storage.{$zone}");
    }

    /**
     * Check if CDN push is enabled.
     */
    public function isPushEnabled(): bool
    {
        return (bool) $this->config->get('cdn.bunny.push_enabled', false);
    }

    /**
     * List files in a storage zone path.
     */
    public function list(string $path, string $zone = 'public'): array
    {
        $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

        if (! $client) {
            return [];
        }

        try {
            return $client->listFiles($path);
        } catch (\Exception $e) {
            Log::error('BunnyStorage: Failed to list files', [
                'path' => $path,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Upload a file to storage zone.
     */
    public function upload(string $localPath, string $remotePath, string $zone = 'public'): bool
    {
        $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

        if (! $client) {
            Log::warning('BunnyStorage: Client not configured', ['zone' => $zone]);

            return false;
        }

        if (! file_exists($localPath)) {
            Log::error('BunnyStorage: Local file not found', ['local' => $localPath]);

            return false;
        }

        $fileSize = filesize($localPath);
        $maxSize = $this->getMaxFileSize();

        if ($fileSize === false || $fileSize > $maxSize) {
            Log::error('BunnyStorage: File size exceeds limit', [
                'local' => $localPath,
                'size' => $fileSize,
                'max_size' => $maxSize,
            ]);

            return false;
        }

        $contentType = $this->detectContentType($localPath);

        return $this->executeWithRetry(function () use ($client, $localPath, $remotePath, $contentType) {
            // The Bunny SDK upload method accepts optional headers parameter
            // Pass content-type for proper CDN handling
            $client->upload($localPath, $remotePath, ['Content-Type' => $contentType]);

            return true;
        }, [
            'local' => $localPath,
            'remote' => $remotePath,
            'zone' => $zone,
            'content_type' => $contentType,
        ], 'Upload');
    }

    /**
     * Get the maximum allowed file size in bytes.
     */
    protected function getMaxFileSize(): int
    {
        return (int) $this->config->get('cdn.bunny.max_file_size', self::DEFAULT_MAX_FILE_SIZE);
    }

    /**
     * Detect the MIME content type for a file.
     *
     * First tries to detect from file contents using PHP's built-in function,
     * then falls back to extension-based detection.
     *
     * @param  string  $path  File path (local or remote)
     * @param  string|null  $contents  File contents for content-based detection
     * @return string MIME type (defaults to application/octet-stream)
     */
    public function detectContentType(string $path, ?string $contents = null): string
    {
        // Try content-based detection if contents provided and finfo available
        if ($contents !== null && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = finfo_buffer($finfo, $contents);
                finfo_close($finfo);
                if ($mimeType !== false && $mimeType !== 'application/octet-stream') {
                    return $mimeType;
                }
            }
        }

        // Try mime_content_type for local files
        if (file_exists($path) && function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($path);
            if ($mimeType !== false && $mimeType !== 'application/octet-stream') {
                return $mimeType;
            }
        }

        // Fall back to extension-based detection
        return $this->getContentTypeFromExtension($path);
    }

    /**
     * Get content type based on file extension.
     *
     * @param  string  $path  File path to extract extension from
     * @return string MIME type (defaults to application/octet-stream)
     */
    public function getContentTypeFromExtension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }

    /**
     * Check if a MIME type is for a binary file.
     */
    public function isBinaryContentType(string $mimeType): bool
    {
        // Text types are not binary
        if (str_starts_with($mimeType, 'text/')) {
            return false;
        }

        // Some application types are text-based
        $textApplicationTypes = [
            'application/json',
            'application/xml',
            'application/javascript',
            'application/x-javascript',
        ];

        return ! in_array($mimeType, $textApplicationTypes, true);
    }

    /**
     * Execute an operation with exponential backoff retry.
     */
    protected function executeWithRetry(callable $operation, array $context, string $operationName): bool
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    $delayMs = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);

                    Log::warning("BunnyStorage: {$operationName} attempt {$attempt} failed, retrying", array_merge($context, [
                        'attempt' => $attempt,
                        'next_delay_ms' => $delayMs * 2,
                    ]));
                }
            }
        }

        Log::error("BunnyStorage: {$operationName} failed after ".self::MAX_RETRY_ATTEMPTS.' attempts', array_merge($context, [
            'error' => $lastException?->getMessage() ?? 'Unknown error',
        ]));

        return false;
    }

    /**
     * Upload file contents directly.
     */
    public function putContents(string $remotePath, string $contents, string $zone = 'public'): bool
    {
        $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

        if (! $client) {
            return false;
        }

        $contentSize = strlen($contents);
        $maxSize = $this->getMaxFileSize();

        if ($contentSize > $maxSize) {
            Log::error('BunnyStorage: Content size exceeds limit', [
                'remote' => $remotePath,
                'size' => $contentSize,
                'max_size' => $maxSize,
            ]);

            return false;
        }

        $contentType = $this->detectContentType($remotePath, $contents);

        return $this->executeWithRetry(function () use ($client, $remotePath, $contents, $contentType) {
            // The Bunny SDK putContents method accepts optional headers parameter
            // Pass content-type for proper CDN handling
            $client->putContents($remotePath, $contents, ['Content-Type' => $contentType]);

            return true;
        }, [
            'remote' => $remotePath,
            'zone' => $zone,
            'content_type' => $contentType,
        ], 'putContents');
    }

    /**
     * Download file contents.
     */
    public function getContents(string $remotePath, string $zone = 'public'): ?string
    {
        $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

        if (! $client) {
            return null;
        }

        try {
            return $client->getContents($remotePath);
        } catch (\Exception $e) {
            Log::error('BunnyStorage: getContents failed', [
                'remote' => $remotePath,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a file from storage zone.
     */
    public function delete(string $remotePath, string $zone = 'public'): bool
    {
        $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

        if (! $client) {
            return false;
        }

        try {
            $client->delete($remotePath);

            return true;
        } catch (\Exception $e) {
            Log::error('BunnyStorage: Delete failed', [
                'remote' => $remotePath,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete multiple files.
     */
    public function deleteMultiple(array $paths, string $zone = 'public'): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->delete($path, $zone);
        }

        return $results;
    }

    /**
     * Copy a file from a Laravel disk to CDN storage zone.
     */
    public function copyFromDisk(string $disk, string $path, string $zone = 'public'): bool
    {
        $diskInstance = Storage::disk($disk);

        if (! $diskInstance->exists($path)) {
            Log::warning('BunnyStorage: Source file not found on disk', [
                'disk' => $disk,
                'path' => $path,
            ]);

            return false;
        }

        $contents = $diskInstance->get($path);

        return $this->putContents($path, $contents, $zone);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // vBucket operations for workspace-isolated CDN paths
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Generate a vBucket ID for a domain/workspace.
     */
    public function vBucketId(string $domain): string
    {
        return LthnHash::vBucketId($domain);
    }

    /**
     * Build a vBucket-scoped path.
     */
    public function vBucketPath(string $domain, string $path): string
    {
        $vBucketId = $this->vBucketId($domain);

        return $vBucketId.'/'.ltrim($path, '/');
    }

    /**
     * Upload content with vBucket scoping.
     */
    public function vBucketPutContents(string $domain, string $path, string $contents, string $zone = 'public'): bool
    {
        $scopedPath = $this->vBucketPath($domain, $path);

        return $this->putContents($scopedPath, $contents, $zone);
    }

    /**
     * Upload file with vBucket scoping.
     */
    public function vBucketUpload(string $domain, string $localPath, string $remotePath, string $zone = 'public'): bool
    {
        $scopedPath = $this->vBucketPath($domain, $remotePath);

        return $this->upload($localPath, $scopedPath, $zone);
    }

    /**
     * Get file contents with vBucket scoping.
     */
    public function vBucketGetContents(string $domain, string $path, string $zone = 'public'): ?string
    {
        $scopedPath = $this->vBucketPath($domain, $path);

        return $this->getContents($scopedPath, $zone);
    }

    /**
     * Delete file with vBucket scoping.
     */
    public function vBucketDelete(string $domain, string $path, string $zone = 'public'): bool
    {
        $scopedPath = $this->vBucketPath($domain, $path);

        return $this->delete($scopedPath, $zone);
    }

    /**
     * List files within a vBucket.
     */
    public function vBucketList(string $domain, string $path = '', string $zone = 'public'): array
    {
        $scopedPath = $this->vBucketPath($domain, $path);

        return $this->list($scopedPath, $zone);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Health Check (implements HealthCheckable)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Perform a health check on the CDN storage zones.
     *
     * Tests connectivity by listing the root directory of configured storage zones.
     * Returns a HealthCheckResult with status, latency, and zone information.
     */
    public function healthCheck(): HealthCheckResult
    {
        $publicConfigured = $this->isConfigured('public');
        $privateConfigured = $this->isConfigured('private');

        if (! $publicConfigured && ! $privateConfigured) {
            return HealthCheckResult::unknown('No CDN storage zones configured');
        }

        $results = [];
        $startTime = microtime(true);
        $hasError = false;
        $isDegraded = false;

        // Check public zone
        if ($publicConfigured) {
            $publicResult = $this->checkZoneHealth('public');
            $results['public'] = $publicResult;
            if (! $publicResult['success']) {
                $hasError = true;
            } elseif ($publicResult['latency_ms'] > 1000) {
                $isDegraded = true;
            }
        }

        // Check private zone
        if ($privateConfigured) {
            $privateResult = $this->checkZoneHealth('private');
            $results['private'] = $privateResult;
            if (! $privateResult['success']) {
                $hasError = true;
            } elseif ($privateResult['latency_ms'] > 1000) {
                $isDegraded = true;
            }
        }

        $totalLatency = (microtime(true) - $startTime) * 1000;

        if ($hasError) {
            return HealthCheckResult::unhealthy(
                'One or more CDN storage zones are unreachable',
                ['zones' => $results],
                $totalLatency
            );
        }

        if ($isDegraded) {
            return HealthCheckResult::degraded(
                'CDN storage zones responding slowly',
                ['zones' => $results],
                $totalLatency
            );
        }

        return HealthCheckResult::healthy(
            'All configured CDN storage zones operational',
            ['zones' => $results],
            $totalLatency
        );
    }

    /**
     * Check health of a specific storage zone.
     *
     * @param  string  $zone  'public' or 'private'
     * @return array{success: bool, latency_ms: float, error?: string}
     */
    protected function checkZoneHealth(string $zone): array
    {
        $startTime = microtime(true);

        try {
            $client = $zone === 'private' ? $this->privateClient() : $this->publicClient();

            if (! $client) {
                return [
                    'success' => false,
                    'latency_ms' => 0,
                    'error' => 'Client not initialized',
                ];
            }

            // List root directory as a simple connectivity check
            // This is a read-only operation that should be fast
            $client->listFiles('/');

            $latencyMs = (microtime(true) - $startTime) * 1000;

            return [
                'success' => true,
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (\Exception $e) {
            $latencyMs = (microtime(true) - $startTime) * 1000;

            Log::warning('BunnyStorage: Health check failed', [
                'zone' => $zone,
                'error' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ]);

            return [
                'success' => false,
                'latency_ms' => round($latencyMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform a quick connectivity check.
     *
     * Simpler than healthCheck() - just returns true/false.
     *
     * @param  string  $zone  'public', 'private', or 'any' (default)
     */
    public function isReachable(string $zone = 'any'): bool
    {
        if ($zone === 'any') {
            // Check if any configured zone is reachable
            if ($this->isConfigured('public')) {
                $result = $this->checkZoneHealth('public');
                if ($result['success']) {
                    return true;
                }
            }

            if ($this->isConfigured('private')) {
                $result = $this->checkZoneHealth('private');
                if ($result['success']) {
                    return true;
                }
            }

            return false;
        }

        if (! $this->isConfigured($zone)) {
            return false;
        }

        $result = $this->checkZoneHealth($zone);

        return $result['success'];
    }
}
