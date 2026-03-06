<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Image;

use Illuminate\Support\Facades\Log;

/**
 * Modern Image Format Detection and Handling.
 *
 * Provides detection and basic handling for modern image formats including
 * HEIC (High Efficiency Image Container) and AVIF (AV1 Image File Format).
 *
 * ## Supported Formats
 *
 * | Format | Extension | MIME Type | Detection | Conversion |
 * |--------|-----------|-----------|-----------|------------|
 * | HEIC   | .heic     | image/heic | Yes | Via Imagick |
 * | HEIF   | .heif     | image/heif | Yes | Via Imagick |
 * | AVIF   | .avif     | image/avif | Yes | Via GD 8.1+ / Imagick |
 *
 * ## Usage
 *
 * ```php
 * $support = new ModernFormatSupport();
 *
 * // Check if a file is a modern format
 * if ($support->isModernFormat($path)) {
 *     $info = $support->getFormatInfo($path);
 *     // ['format' => 'heic', 'mime' => 'image/heic', ...]
 * }
 *
 * // Convert to web-compatible format
 * $jpegPath = $support->convertToJpeg($heicPath);
 * ```
 *
 * ## Requirements
 *
 * - HEIC/HEIF conversion requires Imagick extension with HEIC support
 * - AVIF support requires PHP 8.1+ with GD, or Imagick with AVIF support
 */
class ModernFormatSupport
{
    /**
     * HEIC magic bytes (ftyp box with heic/heix/hevc/hevx brand).
     */
    protected const HEIC_SIGNATURES = [
        'ftyp' => [0x66, 0x74, 0x79, 0x70], // "ftyp" at offset 4
        'heic' => [0x68, 0x65, 0x69, 0x63], // "heic"
        'heix' => [0x68, 0x65, 0x69, 0x78], // "heix"
        'hevc' => [0x68, 0x65, 0x76, 0x63], // "hevc"
        'hevx' => [0x68, 0x65, 0x76, 0x78], // "hevx"
        'mif1' => [0x6D, 0x69, 0x66, 0x31], // "mif1" (HEIF)
        'msf1' => [0x6D, 0x73, 0x66, 0x31], // "msf1" (HEIF sequence)
    ];

    /**
     * AVIF magic bytes (ftyp box with avif/avis brand).
     */
    protected const AVIF_SIGNATURES = [
        'avif' => [0x61, 0x76, 0x69, 0x66], // "avif"
        'avis' => [0x61, 0x76, 0x69, 0x73], // "avis" (sequence)
    ];

    /**
     * Format constants.
     */
    public const FORMAT_HEIC = 'heic';

    public const FORMAT_HEIF = 'heif';

    public const FORMAT_AVIF = 'avif';

    public const FORMAT_UNKNOWN = 'unknown';

    /**
     * MIME type mapping.
     */
    protected const MIME_TYPES = [
        self::FORMAT_HEIC => 'image/heic',
        self::FORMAT_HEIF => 'image/heif',
        self::FORMAT_AVIF => 'image/avif',
    ];

    /**
     * File extension mapping.
     */
    protected const EXTENSIONS = [
        self::FORMAT_HEIC => ['heic', 'heics'],
        self::FORMAT_HEIF => ['heif', 'heifs'],
        self::FORMAT_AVIF => ['avif'],
    ];

    /**
     * Check if the given file is a modern image format (HEIC, HEIF, or AVIF).
     *
     * Uses magic byte detection for reliable format identification.
     */
    public function isModernFormat(string $path): bool
    {
        $format = $this->detectFormat($path);

        return $format !== self::FORMAT_UNKNOWN;
    }

    /**
     * Check if the given file is HEIC format.
     */
    public function isHeic(string $path): bool
    {
        $format = $this->detectFormat($path);

        return in_array($format, [self::FORMAT_HEIC, self::FORMAT_HEIF], true);
    }

    /**
     * Check if the given file is AVIF format.
     */
    public function isAvif(string $path): bool
    {
        return $this->detectFormat($path) === self::FORMAT_AVIF;
    }

    /**
     * Detect the format of a file using magic bytes.
     *
     * @param  string  $path  Absolute file path or binary content
     * @return string One of the FORMAT_* constants
     */
    public function detectFormat(string $path): string
    {
        $bytes = $this->readMagicBytes($path);

        if ($bytes === null || strlen($bytes) < 12) {
            return self::FORMAT_UNKNOWN;
        }

        // Check for ftyp box (ISO Base Media File Format)
        // Structure: [4 bytes size][4 bytes "ftyp"][4 bytes brand]
        $ftypOffset = 4;
        $brandOffset = 8;

        // Verify ftyp marker
        if (! $this->matchesSignature($bytes, $ftypOffset, self::HEIC_SIGNATURES['ftyp'])) {
            return self::FORMAT_UNKNOWN;
        }

        // Check brand for AVIF
        foreach (self::AVIF_SIGNATURES as $signature) {
            if ($this->matchesSignature($bytes, $brandOffset, $signature)) {
                return self::FORMAT_AVIF;
            }
        }

        // Check brand for HEIC/HEIF
        foreach (['heic', 'heix', 'hevc', 'hevx'] as $brand) {
            if ($this->matchesSignature($bytes, $brandOffset, self::HEIC_SIGNATURES[$brand])) {
                return self::FORMAT_HEIC;
            }
        }

        // Check for HEIF (mif1, msf1)
        foreach (['mif1', 'msf1'] as $brand) {
            if ($this->matchesSignature($bytes, $brandOffset, self::HEIC_SIGNATURES[$brand])) {
                return self::FORMAT_HEIF;
            }
        }

        return self::FORMAT_UNKNOWN;
    }

    /**
     * Detect format from binary content (not a file path).
     */
    public function detectFormatFromContent(string $content): string
    {
        if (strlen($content) < 12) {
            return self::FORMAT_UNKNOWN;
        }

        return $this->detectFormatFromBytes($content);
    }

    /**
     * Get detailed format information for a file.
     *
     * @return array{format: string, mime: string, extension: string, supported: bool, conversion_available: bool}|null
     */
    public function getFormatInfo(string $path): ?array
    {
        $format = $this->detectFormat($path);

        if ($format === self::FORMAT_UNKNOWN) {
            return null;
        }

        return [
            'format' => $format,
            'mime' => self::MIME_TYPES[$format] ?? 'application/octet-stream',
            'extension' => self::EXTENSIONS[$format][0] ?? $format,
            'supported' => $this->isFormatSupported($format),
            'conversion_available' => $this->canConvert($format),
        ];
    }

    /**
     * Check if PHP can natively read this format.
     */
    public function isFormatSupported(string $format): bool
    {
        return match ($format) {
            self::FORMAT_AVIF => $this->isAvifSupported(),
            self::FORMAT_HEIC, self::FORMAT_HEIF => $this->isHeicSupported(),
            default => false,
        };
    }

    /**
     * Check if AVIF format is supported by the current PHP installation.
     *
     * AVIF support was added in GD 2.3.0 (PHP 8.1+) and modern Imagick.
     */
    public function isAvifSupported(): bool
    {
        // Check GD support (PHP 8.1+)
        if (extension_loaded('gd')) {
            $gdInfo = gd_info();
            if (! empty($gdInfo['AVIF Support'])) {
                return true;
            }
        }

        // Check Imagick support
        if (extension_loaded('imagick')) {
            try {
                $formats = \Imagick::queryFormats('AVIF');

                return ! empty($formats);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check if HEIC format is supported by the current PHP installation.
     *
     * HEIC support requires Imagick compiled with libheif.
     */
    public function isHeicSupported(): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats('HEIC');

            return ! empty($formats);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if we can convert this format to a web-compatible format.
     */
    public function canConvert(string $format): bool
    {
        return $this->isFormatSupported($format);
    }

    /**
     * Convert a modern format image to JPEG.
     *
     * @param  int  $quality  JPEG quality (0-100)
     * @return string|null Path to converted file, or null on failure
     */
    public function convertToJpeg(string $sourcePath, ?string $destPath = null, int $quality = 85): ?string
    {
        $format = $this->detectFormat($sourcePath);

        if ($format === self::FORMAT_UNKNOWN) {
            Log::warning('ModernFormatSupport: Cannot convert unknown format', ['path' => $sourcePath]);

            return null;
        }

        if (! $this->canConvert($format)) {
            Log::warning('ModernFormatSupport: Conversion not available for format', [
                'format' => $format,
                'path' => $sourcePath,
            ]);

            return null;
        }

        // Generate destination path if not provided
        $destPath ??= $this->generateDestPath($sourcePath, 'jpg');

        try {
            if ($format === self::FORMAT_AVIF && $this->isAvifSupported()) {
                return $this->convertAvifToJpeg($sourcePath, $destPath, $quality);
            }

            if (in_array($format, [self::FORMAT_HEIC, self::FORMAT_HEIF], true)) {
                return $this->convertHeicToJpeg($sourcePath, $destPath, $quality);
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('ModernFormatSupport: Conversion failed', [
                'format' => $format,
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert a modern format image to WebP.
     *
     * @param  int  $quality  WebP quality (0-100)
     * @return string|null Path to converted file, or null on failure
     */
    public function convertToWebp(string $sourcePath, ?string $destPath = null, int $quality = 85): ?string
    {
        $format = $this->detectFormat($sourcePath);

        if ($format === self::FORMAT_UNKNOWN || ! $this->canConvert($format)) {
            return null;
        }

        $destPath ??= $this->generateDestPath($sourcePath, 'webp');

        try {
            if ($format === self::FORMAT_AVIF && $this->isAvifSupported()) {
                return $this->convertAvifToWebp($sourcePath, $destPath, $quality);
            }

            if (in_array($format, [self::FORMAT_HEIC, self::FORMAT_HEIF], true)) {
                return $this->convertHeicToWebp($sourcePath, $destPath, $quality);
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('ModernFormatSupport: WebP conversion failed', [
                'format' => $format,
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert AVIF to JPEG using GD or Imagick.
     */
    protected function convertAvifToJpeg(string $source, string $dest, int $quality): string
    {
        // Try GD first (faster)
        if (extension_loaded('gd') && function_exists('imagecreatefromavif')) {
            $image = imagecreatefromavif($source);
            if ($image !== false) {
                imagejpeg($image, $dest, $quality);
                imagedestroy($image);

                return $dest;
            }
        }

        // Fall back to Imagick
        if (extension_loaded('imagick')) {
            $imagick = new \Imagick($source);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($quality);
            $imagick->writeImage($dest);
            $imagick->destroy();

            return $dest;
        }

        throw new \RuntimeException('No suitable driver available for AVIF conversion');
    }

    /**
     * Convert AVIF to WebP using GD or Imagick.
     */
    protected function convertAvifToWebp(string $source, string $dest, int $quality): string
    {
        // Try GD first
        if (extension_loaded('gd') && function_exists('imagecreatefromavif')) {
            $image = imagecreatefromavif($source);
            if ($image !== false) {
                imagewebp($image, $dest, $quality);
                imagedestroy($image);

                return $dest;
            }
        }

        // Fall back to Imagick
        if (extension_loaded('imagick')) {
            $imagick = new \Imagick($source);
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);
            $imagick->writeImage($dest);
            $imagick->destroy();

            return $dest;
        }

        throw new \RuntimeException('No suitable driver available for AVIF to WebP conversion');
    }

    /**
     * Convert HEIC to JPEG using Imagick.
     */
    protected function convertHeicToJpeg(string $source, string $dest, int $quality): string
    {
        if (! extension_loaded('imagick')) {
            throw new \RuntimeException('Imagick extension required for HEIC conversion');
        }

        $imagick = new \Imagick($source);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality($quality);
        $imagick->writeImage($dest);
        $imagick->destroy();

        return $dest;
    }

    /**
     * Convert HEIC to WebP using Imagick.
     */
    protected function convertHeicToWebp(string $source, string $dest, int $quality): string
    {
        if (! extension_loaded('imagick')) {
            throw new \RuntimeException('Imagick extension required for HEIC conversion');
        }

        $imagick = new \Imagick($source);
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality($quality);
        $imagick->writeImage($dest);
        $imagick->destroy();

        return $dest;
    }

    /**
     * Get image dimensions from a modern format file.
     *
     * @return array{width: int, height: int}|null
     */
    public function getImageDimensions(string $path): ?array
    {
        $format = $this->detectFormat($path);

        if ($format === self::FORMAT_UNKNOWN) {
            return null;
        }

        // Try Imagick first (most reliable)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick($path);
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();
                $imagick->destroy();

                return ['width' => $width, 'height' => $height];
            } catch (\Throwable) {
                // Fall through to GD
            }
        }

        // Try GD for AVIF
        if ($format === self::FORMAT_AVIF && function_exists('imagecreatefromavif')) {
            try {
                $image = @imagecreatefromavif($path);
                if ($image !== false) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    imagedestroy($image);

                    return ['width' => $width, 'height' => $height];
                }
            } catch (\Throwable) {
                // Fall through
            }
        }

        return null;
    }

    /**
     * Get the MIME type for a format.
     */
    public function getMimeType(string $format): string
    {
        return self::MIME_TYPES[$format] ?? 'application/octet-stream';
    }

    /**
     * Get the file extension for a format.
     */
    public function getExtension(string $format): string
    {
        return self::EXTENSIONS[$format][0] ?? $format;
    }

    /**
     * Check if a file extension indicates a modern format.
     */
    public function isModernExtension(string $extension): bool
    {
        $extension = strtolower($extension);

        foreach (self::EXTENSIONS as $extensions) {
            if (in_array($extension, $extensions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available format support status.
     *
     * @return array{avif: bool, heic: bool, heif: bool}
     */
    public function getSupportStatus(): array
    {
        return [
            'avif' => $this->isAvifSupported(),
            'heic' => $this->isHeicSupported(),
            'heif' => $this->isHeicSupported(),
        ];
    }

    /**
     * Read the first bytes of a file for magic byte detection.
     */
    protected function readMagicBytes(string $path): ?string
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $bytes = fread($handle, 32);
        fclose($handle);

        return $bytes !== false ? $bytes : null;
    }

    /**
     * Detect format from raw bytes.
     */
    protected function detectFormatFromBytes(string $bytes): string
    {
        $ftypOffset = 4;
        $brandOffset = 8;

        // Verify ftyp marker
        if (! $this->matchesSignature($bytes, $ftypOffset, self::HEIC_SIGNATURES['ftyp'])) {
            return self::FORMAT_UNKNOWN;
        }

        // Check AVIF
        foreach (self::AVIF_SIGNATURES as $signature) {
            if ($this->matchesSignature($bytes, $brandOffset, $signature)) {
                return self::FORMAT_AVIF;
            }
        }

        // Check HEIC
        foreach (['heic', 'heix', 'hevc', 'hevx'] as $brand) {
            if ($this->matchesSignature($bytes, $brandOffset, self::HEIC_SIGNATURES[$brand])) {
                return self::FORMAT_HEIC;
            }
        }

        // Check HEIF
        foreach (['mif1', 'msf1'] as $brand) {
            if ($this->matchesSignature($bytes, $brandOffset, self::HEIC_SIGNATURES[$brand])) {
                return self::FORMAT_HEIF;
            }
        }

        return self::FORMAT_UNKNOWN;
    }

    /**
     * Check if bytes match a signature at the given offset.
     *
     * @param  array<int>  $signature
     */
    protected function matchesSignature(string $bytes, int $offset, array $signature): bool
    {
        if (strlen($bytes) < $offset + count($signature)) {
            return false;
        }

        foreach ($signature as $i => $byte) {
            if (ord($bytes[$offset + $i]) !== $byte) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate destination path for converted file.
     */
    protected function generateDestPath(string $sourcePath, string $extension): string
    {
        $dir = dirname($sourcePath);
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);

        return $dir.DIRECTORY_SEPARATOR.$filename.'.'.$extension;
    }
}
