<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * File handling utilities for uploads and remote fetching.
 *
 * Provides helpers for converting base64 to UploadedFile,
 * fetching remote URLs, and extracting filenames from HTTP responses.
 */
class File
{
    /**
     * Convert base64 data URI to UploadedFile instance.
     *
     * Handles data URIs in format: data:mime/type;base64,<encoded_data>
     */
    public static function fromBase64(string $base64File, ?string $filename = null): UploadedFile
    {
        // Extract base64 data after comma
        $fileData = base64_decode(Arr::last(explode(',', $base64File)));

        // Create temporary file
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        // Write decoded data to temp file
        file_put_contents($tempFilePath, $fileData);

        $tempFileObject = new HttpFile($tempFilePath);

        $file = new UploadedFile(
            $tempFileObject->getPathname(),
            $filename ?: $tempFileObject->getFilename(),
            $tempFileObject->getMimeType(),
            0,
            true // Mark as test file (not from real HTTP POST)
        );

        // Clean up temp file after request completes
        app()->terminating(function () use ($tempFile) {
            fclose($tempFile);
        });

        return $file;
    }

    /**
     * Fetch content from URL.
     */
    public static function fetchUrl(string $url, int $timeout = 10): PromiseInterface|Response
    {
        return Http::timeout($timeout)->get($url);
    }

    /**
     * Extract filename from HTTP response headers or URL.
     *
     * Tries Content-Disposition header first, then falls back to URL path.
     */
    public static function getFilenameFromHttpResponse(
        Response $response,
        ?string $fallbackFilename = null
    ): string {
        $contentDisposition = $response->header('Content-Disposition');

        if ($contentDisposition) {
            // Try standard filename format
            if (preg_match('/filename="([^"]+)"/', $contentDisposition, $matches)) {
                return $matches[1];
            }

            // Try RFC 5987 encoded filename
            if (preg_match('/filename\*=UTF-8\'\'([^;]+)/', $contentDisposition, $matches)) {
                return rawurldecode($matches[1]);
            }
        }

        // Fall back to URL path
        $urlPath = parse_url($response->effectiveUri(), PHP_URL_PATH);

        if ($urlPath) {
            return basename($urlPath);
        }

        return $fallbackFilename ?: Str::random(40);
    }
}
