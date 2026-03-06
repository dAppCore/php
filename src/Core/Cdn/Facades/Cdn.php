<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Facades;

use Core\Cdn\Services\StorageUrlResolver;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string cdn(string $path)
 * @method static string origin(string $path)
 * @method static string private(string $path)
 * @method static string|null signedUrl(string $path, int|\Carbon\Carbon|null $expiry = null)
 * @method static string apex(string $path)
 * @method static string asset(string $path, ?string $context = null)
 * @method static array urls(string $path)
 * @method static array allUrls(string $path)
 * @method static string detectContext()
 * @method static bool isAdminContext()
 * @method static bool pushToCdn(string $disk, string $path, string $zone = 'public')
 * @method static bool deleteFromCdn(string $path, string $zone = 'public')
 * @method static bool purge(string $path)
 * @method static string cachedAsset(string $path, ?string $context = null)
 * @method static \Illuminate\Contracts\Filesystem\Filesystem publicDisk()
 * @method static \Illuminate\Contracts\Filesystem\Filesystem privateDisk()
 * @method static bool storePublic(string $path, mixed $contents, bool $pushToCdn = true)
 * @method static bool storePrivate(string $path, mixed $contents, bool $pushToCdn = true)
 * @method static bool deleteAsset(string $path, string $bucket = 'public')
 * @method static string pathPrefix(string $category)
 * @method static string categoryPath(string $category, string $path)
 * @method static string vBucketId(string $domain)
 * @method static string vBucketCdn(string $domain, string $path)
 * @method static string vBucketOrigin(string $domain, string $path)
 * @method static string vBucketPath(string $domain, string $path)
 * @method static array vBucketUrls(string $domain, string $path)
 *
 * @see \Core\Cdn\Services\StorageUrlResolver
 */
class Cdn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StorageUrlResolver::class;
    }
}
