<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Core\Config\ConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BunnyCDN Pull Zone API service.
 *
 * Handles CDN operations via BunnyCDN API:
 * - Cache purging (URL, tag, workspace, global)
 * - Statistics retrieval
 * - Pull zone management
 *
 * ## Methods
 *
 * | Method | Returns | Description |
 * |--------|---------|-------------|
 * | `isConfigured()` | `bool` | Check if BunnyCDN is configured |
 * | `purgeUrl()` | `bool` | Purge a single URL from cache |
 * | `purgeUrls()` | `bool` | Purge multiple URLs from cache |
 * | `purgeAll()` | `bool` | Purge entire pull zone cache |
 * | `purgeByTag()` | `bool` | Purge cache by tag |
 * | `purgeWorkspace()` | `bool` | Purge all cached content for a workspace |
 * | `getStats()` | `array\|null` | Get CDN statistics for pull zone |
 * | `getBandwidth()` | `array\|null` | Get bandwidth usage for pull zone |
 * | `listStorageFiles()` | `array\|null` | List files in storage zone |
 * | `uploadFile()` | `bool` | Upload a file to storage zone |
 * | `deleteFile()` | `bool` | Delete a file from storage zone |
 */
class BunnyCdnService
{
    protected string $apiKey;

    protected string $pullZoneId;

    protected string $baseUrl = 'https://api.bunny.net';

    public function __construct(
        protected ConfigService $config,
    ) {
        $this->apiKey = $this->config->get('cdn.bunny.api_key') ?? '';
        $this->pullZoneId = $this->config->get('cdn.bunny.pull_zone_id') ?? '';
    }

    /**
     * Sanitize an error message to remove sensitive data like API keys.
     *
     * @param  string  $message  The error message to sanitize
     * @return string The sanitized message with API keys replaced by [REDACTED]
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        $sensitiveKeys = array_filter([
            $this->apiKey,
            $this->config->get('cdn.bunny.storage.public.api_key'),
            $this->config->get('cdn.bunny.storage.private.api_key'),
        ]);

        foreach ($sensitiveKeys as $key) {
            if ($key !== '' && str_contains($message, $key)) {
                $message = str_replace($key, '[REDACTED]', $message);
            }
        }

        return $message;
    }

    /**
     * Check if the service is configured.
     *
     * @return bool True if BunnyCDN API key and pull zone ID are configured
     */
    public function isConfigured(): bool
    {
        return $this->config->isConfigured('cdn.bunny');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Cache Purging
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Purge a single URL from CDN cache.
     *
     * @param  string  $url  The full URL to purge from cache
     * @return bool True if purge was successful, false otherwise
     */
    public function purgeUrl(string $url): bool
    {
        return $this->purgeUrls([$url]);
    }

    /**
     * Purge multiple URLs from CDN cache.
     *
     * @param  array<string>  $urls  Array of full URLs to purge from cache
     * @return bool True if all purges were successful, false if any failed
     */
    public function purgeUrls(array $urls): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('BunnyCDN: Cannot purge - not configured');

            return false;
        }

        try {
            foreach ($urls as $url) {
                $response = Http::withHeaders([
                    'AccessKey' => $this->apiKey,
                ])->post("{$this->baseUrl}/purge", [
                    'url' => $url,
                ]);

                if (! $response->successful()) {
                    Log::error('BunnyCDN: Purge failed', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('BunnyCDN: Purge exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return false;
        }
    }

    /**
     * Purge entire pull zone cache.
     *
     * @return bool True if purge was successful, false otherwise
     */
    public function purgeAll(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->post("{$this->baseUrl}/pullzone/{$this->pullZoneId}/purgeCache");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('BunnyCDN: PurgeAll exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return false;
        }
    }

    /**
     * Purge cache by tag.
     *
     * @param  string  $tag  The cache tag to purge (e.g., 'workspace-uuid')
     * @return bool True if purge was successful, false otherwise
     */
    public function purgeByTag(string $tag): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->post("{$this->baseUrl}/pullzone/{$this->pullZoneId}/purgeCache", [
                'CacheTag' => $tag,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('BunnyCDN: PurgeByTag exception', [
                'tag' => $tag,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Purge all cached content for a workspace.
     *
     * @param  object  $workspace  Workspace model instance (requires uuid property)
     * @return bool True if purge was successful, false otherwise
     */
    public function purgeWorkspace(object $workspace): bool
    {
        return $this->purgeByTag("workspace-{$workspace->uuid}");
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Statistics
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Get CDN statistics for pull zone.
     *
     * @param  string|null  $dateFrom  Start date in YYYY-MM-DD format
     * @param  string|null  $dateTo  End date in YYYY-MM-DD format
     * @return array<string, mixed>|null Statistics array or null on failure
     */
    public function getStats(?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $params = [
                'pullZone' => $this->pullZoneId,
            ];

            if ($dateFrom) {
                $params['dateFrom'] = $dateFrom;
            }
            if ($dateTo) {
                $params['dateTo'] = $dateTo;
            }

            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->get("{$this->baseUrl}/statistics", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('BunnyCDN: GetStats exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return null;
        }
    }

    /**
     * Get bandwidth usage for pull zone.
     *
     * @param  string|null  $dateFrom  Start date in YYYY-MM-DD format
     * @param  string|null  $dateTo  End date in YYYY-MM-DD format
     * @return array{total_bandwidth: int, cached_bandwidth: int, origin_bandwidth: int}|null Bandwidth stats or null on failure
     */
    public function getBandwidth(?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $stats = $this->getStats($dateFrom, $dateTo);

        if (! $stats) {
            return null;
        }

        return [
            'total_bandwidth' => $stats['TotalBandwidthUsed'] ?? 0,
            'cached_bandwidth' => $stats['CacheHitRate'] ?? 0,
            'origin_bandwidth' => $stats['TotalOriginTraffic'] ?? 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Storage Zone Operations (via API)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * List files in a storage zone via API.
     *
     * Note: For direct storage operations, use BunnyStorageService instead.
     *
     * @param  string  $storageZoneName  Name of the storage zone
     * @param  string  $path  Path within the storage zone (default: root)
     * @return array<int, array<string, mixed>>|null Array of file objects or null on failure
     */
    public function listStorageFiles(string $storageZoneName, string $path = '/'): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $storageApiKey = $this->config->get('cdn.bunny.storage.public.api_key');
            $region = $this->config->get('cdn.bunny.storage.public.hostname', 'storage.bunnycdn.com');

            $url = "https://{$region}/{$storageZoneName}/{$path}";

            $response = Http::withHeaders([
                'AccessKey' => $storageApiKey,
            ])->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('BunnyCDN: ListStorageFiles exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return null;
        }
    }

    /**
     * Upload a file to storage zone via API.
     *
     * Note: For direct storage operations, use BunnyStorageService instead.
     *
     * @param  string  $storageZoneName  Name of the storage zone
     * @param  string  $path  Target path within the storage zone
     * @param  string  $contents  File contents to upload
     * @return bool True if upload was successful, false otherwise
     */
    public function uploadFile(string $storageZoneName, string $path, string $contents): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $storageApiKey = $this->config->get('cdn.bunny.storage.public.api_key');
            $region = $this->config->get('cdn.bunny.storage.public.hostname', 'storage.bunnycdn.com');

            $url = "https://{$region}/{$storageZoneName}/{$path}";

            $response = Http::withHeaders([
                'AccessKey' => $storageApiKey,
                'Content-Type' => 'application/octet-stream',
            ])->withBody($contents, 'application/octet-stream')->put($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('BunnyCDN: UploadFile exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return false;
        }
    }

    /**
     * Delete a file from storage zone via API.
     *
     * Note: For direct storage operations, use BunnyStorageService instead.
     *
     * @param  string  $storageZoneName  Name of the storage zone
     * @param  string  $path  Path of the file to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteFile(string $storageZoneName, string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $storageApiKey = $this->config->get('cdn.bunny.storage.public.api_key');
            $region = $this->config->get('cdn.bunny.storage.public.hostname', 'storage.bunnycdn.com');

            $url = "https://{$region}/{$storageZoneName}/{$path}";

            $response = Http::withHeaders([
                'AccessKey' => $storageApiKey,
            ])->delete($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('BunnyCDN: DeleteFile exception', ['error' => $this->sanitizeErrorMessage($e->getMessage())]);

            return false;
        }
    }
}
