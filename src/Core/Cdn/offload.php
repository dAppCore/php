<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Offload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for offloading uploads to S3-compatible storage.
    | Supports AWS S3, Hetzner Object Storage, and any S3-compatible endpoint.
    |
    */

    /**
     * Enable storage offload globally.
     */
    'enabled' => env('STORAGE_OFFLOAD_ENABLED', false),

    /**
     * Default disk for offloading.
     * Must be defined in config/filesystems.php
     *
     * Options: 'hetzner', 's3', or any custom S3-compatible disk
     */
    'disk' => env('STORAGE_OFFLOAD_DISK', 'hetzner'),

    /**
     * Base URL for serving offloaded assets.
     * Can be a CDN URL (e.g., BunnyCDN pull zone).
     *
     * If null, uses the disk's configured URL.
     */
    'cdn_url' => env('STORAGE_OFFLOAD_CDN_URL'),

    /**
     * Hetzner Object Storage Configuration.
     */
    'hetzner' => [
        'endpoint' => env('HETZNER_ENDPOINT', 'https://fsn1.your-objectstorage.com'),
        'region' => env('HETZNER_REGION', 'fsn1'),
        'bucket' => env('HETZNER_BUCKET'),
        'access_key' => env('HETZNER_ACCESS_KEY'),
        'secret_key' => env('HETZNER_SECRET_KEY'),
        'visibility' => 'public',
    ],

    /**
     * File path organisation within bucket.
     */
    'paths' => [
        'page' => 'pages',
        'avatar' => 'avatars',
        'media' => 'media',
        'static' => 'static',
    ],

    /**
     * File types eligible for offloading.
     */
    'allowed_extensions' => [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        // Media
        'mp4', 'webm', 'mp3', 'wav', 'ogg',
        // Archives
        'zip', 'tar', 'gz',
    ],

    /**
     * Maximum file size for offload (bytes).
     * Files larger than this will remain local.
     */
    'max_file_size' => env('STORAGE_OFFLOAD_MAX_SIZE', 100 * 1024 * 1024), // 100MB

    /**
     * Automatically offload on upload.
     * If false, manual migration via artisan command required.
     */
    'auto_offload' => env('STORAGE_OFFLOAD_AUTO', true),

    /**
     * Keep local copy after offloading.
     * Useful for gradual migration or backup purposes.
     */
    'keep_local' => env('STORAGE_OFFLOAD_KEEP_LOCAL', false),

    /**
     * Queue configuration for async offloading.
     */
    'queue' => [
        'enabled' => env('STORAGE_OFFLOAD_QUEUE', true),
        'connection' => env('STORAGE_OFFLOAD_QUEUE_CONNECTION', 'redis'),
        'name' => env('STORAGE_OFFLOAD_QUEUE_NAME', 'storage-offload'),
    ],

    /**
     * Cache configuration for URL lookups.
     */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'storage_offload',
    ],

];
