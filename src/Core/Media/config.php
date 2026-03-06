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
    | Queue Settings for Large Files
    |--------------------------------------------------------------------------
    |
    | Configure automatic queueing of media conversions for large files.
    | Files exceeding the threshold will be processed asynchronously.
    |
    */

    /*
    | File size threshold for queueing conversions (in MB).
    | Files larger than this will be processed via queue.
    | Set to 0 to disable automatic queueing.
    */
    'queue_threshold_mb' => (int) env('MEDIA_QUEUE_THRESHOLD_MB', 5),

    /*
    | Queue name for media conversions.
    */
    'queue_name' => env('MEDIA_QUEUE_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Image Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic image optimization for uploaded images.
    | Optimization reduces file sizes while maintaining visual quality.
    |
    */

    'optimization' => [

        /*
        | Enable or disable automatic image optimization.
        */
        'enabled' => env('IMAGE_OPTIMIZATION_ENABLED', true),

        /*
        | Image processing driver: 'gd' or 'imagick'
        | Falls back to 'gd' if selected driver is unavailable.
        */
        'driver' => env('IMAGE_OPTIMIZATION_DRIVER', 'gd'),

        /*
        | JPEG/WebP quality setting (0-100).
        | Lower values = smaller files, lower quality.
        | Recommended: 75-85 for web, 80 is a good default.
        */
        'quality' => (int) env('IMAGE_OPTIMIZATION_QUALITY', 80),

        /*
        | PNG compression level (0-9).
        | Higher values = smaller files, slower processing.
        | 0 = no compression, 9 = maximum compression.
        | Recommended: 6-8.
        */
        'png_compression' => (int) env('IMAGE_OPTIMIZATION_PNG_COMPRESSION', 6),

        /*
        | Minimum file size to optimize (KB).
        | Files smaller than this are skipped to avoid overhead.
        | Default: 10KB.
        */
        'min_size_kb' => (int) env('IMAGE_OPTIMIZATION_MIN_SIZE_KB', 10),

        /*
        | Maximum file size to optimize (MB).
        | Files larger than this are skipped to avoid memory issues.
        | Default: 10MB.
        */
        'max_size_mb' => (int) env('IMAGE_OPTIMIZATION_MAX_SIZE_MB', 10),

    ],

    /*
    |--------------------------------------------------------------------------
    | EXIF Data Settings
    |--------------------------------------------------------------------------
    |
    | Configure EXIF metadata handling for uploaded images.
    | EXIF data can contain sensitive information like GPS coordinates.
    |
    */

    'exif' => [

        /*
        | Strip EXIF data from uploaded images for privacy.
        | Set to false if you need to preserve camera/location data.
        */
        'strip_exif' => env('MEDIA_STRIP_EXIF', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Progressive JPEG Settings
    |--------------------------------------------------------------------------
    |
    | Configure progressive JPEG encoding for better perceived loading.
    | Progressive JPEGs display a low-quality preview first, then sharpen.
    |
    */

    'progressive_jpeg' => env('MEDIA_PROGRESSIVE_JPEG', true),

    /*
    |--------------------------------------------------------------------------
    | Modern Image Format Settings (HEIC/AVIF)
    |--------------------------------------------------------------------------
    |
    | Configure handling of modern image formats like HEIC and AVIF.
    | These formats offer better compression but may not be universally supported.
    |
    */

    'modern_formats' => [

        /*
        | Enable automatic detection of modern image formats.
        */
        'detection_enabled' => env('MEDIA_MODERN_FORMAT_DETECTION', true),

        /*
        | Automatically convert modern formats to web-compatible formats.
        | When enabled, HEIC/AVIF uploads will be converted to JPEG or WebP.
        */
        'auto_convert' => env('MEDIA_MODERN_FORMAT_AUTO_CONVERT', true),

        /*
        | Target format for automatic conversion.
        | Options: 'jpeg', 'webp'
        */
        'convert_to' => env('MEDIA_MODERN_FORMAT_CONVERT_TO', 'jpeg'),

        /*
        | Quality for converted images (0-100).
        */
        'convert_quality' => (int) env('MEDIA_MODERN_FORMAT_CONVERT_QUALITY', 85),

        /*
        | Keep original modern format file after conversion.
        | Set to true to preserve the original HEIC/AVIF alongside the converted version.
        */
        'keep_original' => env('MEDIA_MODERN_FORMAT_KEEP_ORIGINAL', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Lazy Thumbnail Generation
    |--------------------------------------------------------------------------
    |
    | Configure on-demand thumbnail generation. Thumbnails are generated when
    | first requested rather than eagerly on upload, improving upload performance
    | and reducing storage for unused sizes.
    |
    */

    'lazy_thumbnails' => [

        /*
        | Enable lazy thumbnail generation.
        | When disabled, the thumbnail route will return 503 Service Unavailable.
        */
        'enabled' => env('MEDIA_LAZY_THUMBNAILS_ENABLED', true),

        /*
        | Storage disk for source images.
        */
        'source_disk' => env('MEDIA_LAZY_THUMBNAILS_SOURCE_DISK', 'public'),

        /*
        | Storage disk for generated thumbnails.
        */
        'thumbnail_disk' => env('MEDIA_LAZY_THUMBNAILS_THUMBNAIL_DISK', 'public'),

        /*
        | Directory prefix for thumbnail storage.
        | Thumbnails are organized in hash-based subdirectories under this prefix.
        */
        'prefix' => env('MEDIA_LAZY_THUMBNAILS_PREFIX', 'thumbnails'),

        /*
        | JPEG/WebP quality for generated thumbnails (0-100).
        */
        'quality' => (int) env('MEDIA_LAZY_THUMBNAILS_QUALITY', 85),

        /*
        | File size threshold for queueing generation (in KB).
        | Source images larger than this will be processed via queue.
        | Set to 0 to disable automatic queueing (always generate synchronously).
        */
        'queue_threshold_kb' => (int) env('MEDIA_LAZY_THUMBNAILS_QUEUE_THRESHOLD_KB', 500),

        /*
        | Queue name for async thumbnail generation.
        */
        'queue_name' => env('MEDIA_LAZY_THUMBNAILS_QUEUE_NAME', 'default'),

        /*
        | Cache TTL for thumbnail paths (in seconds).
        | How long to cache the path of generated thumbnails.
        | Default: 86400 (24 hours).
        */
        'cache_ttl' => (int) env('MEDIA_LAZY_THUMBNAILS_CACHE_TTL', 86400),

        /*
        | Browser cache TTL (in seconds).
        | How long browsers should cache served thumbnails.
        | Default: 604800 (7 days).
        */
        'browser_cache_ttl' => (int) env('MEDIA_LAZY_THUMBNAILS_BROWSER_CACHE_TTL', 604800),

        /*
        | Placeholder image path or URL.
        | Shown while thumbnails are being generated asynchronously.
        | Set to null to use a generated SVG placeholder.
        | Can be a storage path or full URL.
        */
        'placeholder' => env('MEDIA_LAZY_THUMBNAILS_PLACEHOLDER'),

        /*
        | Placeholder background color for SVG placeholders.
        */
        'placeholder_color' => env('MEDIA_LAZY_THUMBNAILS_PLACEHOLDER_COLOR', '#e5e7eb'),

        /*
        | Minimum allowed thumbnail dimension (pixels).
        */
        'min_dimension' => (int) env('MEDIA_LAZY_THUMBNAILS_MIN_DIMENSION', 10),

        /*
        | Maximum allowed thumbnail width (pixels).
        | Requests for larger dimensions will be rejected.
        */
        'max_width' => (int) env('MEDIA_LAZY_THUMBNAILS_MAX_WIDTH', 2000),

        /*
        | Maximum allowed thumbnail height (pixels).
        */
        'max_height' => (int) env('MEDIA_LAZY_THUMBNAILS_MAX_HEIGHT', 2000),

    ],

];
