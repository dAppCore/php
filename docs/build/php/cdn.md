# CDN Integration

Core PHP provides unified CDN integration for BunnyCDN and Cloudflare with automatic asset offloading, URL generation, and cache management.

## Configuration

```php
// config/cdn.php
return [
    'driver' => env('CDN_DRIVER', 'bunnycdn'),

    'bunnycdn' => [
        'api_key' => env('BUNNY_API_KEY'),
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'storage_password' => env('BUNNY_STORAGE_PASSWORD'),
        'cdn_url' => env('BUNNY_CDN_URL'),
        'pull_zone_id' => env('BUNNY_PULL_ZONE_ID'),
    ],

    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'cdn_url' => env('CLOUDFLARE_CDN_URL'),
    ],

    'offload' => [
        'enabled' => env('CDN_OFFLOAD_ENABLED', false),
        'paths' => ['public/images', 'public/media', 'storage/app/public'],
    ],
];
```

## Basic Usage

### Generating CDN URLs

```php
use Core\Cdn\Facades\Cdn;

// Generate CDN URL
$url = Cdn::url('images/photo.jpg');
// https://cdn.example.com/images/photo.jpg

// With transformation parameters
$url = Cdn::url('images/photo.jpg', [
    'width' => 800,
    'quality' => 85,
]);
```

### Helper Function

```php
// Global helper
$url = cdn_url('images/photo.jpg');

// In Blade templates
<img src="{{ cdn_url('images/photo.jpg') }}" alt="Photo">
```

### Storing Files

```php
// Upload file to CDN
$path = Cdn::store($uploadedFile, 'media');

// Store with custom filename
$path = Cdn::store($uploadedFile, 'media', 'custom-name.jpg');

// Store from contents
$path = Cdn::put('path/file.txt', $contents);
```

### Deleting Files

```php
// Delete single file
Cdn::delete('media/photo.jpg');

// Delete multiple files
Cdn::delete(['media/photo1.jpg', 'media/photo2.jpg']);

// Delete directory
Cdn::deleteDirectory('media/old');
```

## Cache Purging

### Purge Single File

```php
// Purge specific file from CDN cache
Cdn::purge('images/photo.jpg');
```

### Purge Multiple Files

```php
// Purge multiple files
Cdn::purge([
    'images/photo1.jpg',
    'images/photo2.jpg',
]);
```

### Purge by Pattern

```php
// Purge all images
Cdn::purge('images/*');

// Purge all JPEGs
Cdn::purge('**/*.jpg');
```

### Purge Everything

```php
// Purge entire CDN cache (use sparingly!)
Cdn::purgeAll();
```

## Asset Offloading

Automatically offload existing assets to CDN:

```bash
# Offload public disk
php artisan storage:offload --disk=public

# Offload specific path
php artisan storage:offload --path=public/images

# Dry run (preview without uploading)
php artisan storage:offload --dry-run
```

### Programmatic Offloading

```php
use Core\Cdn\Services\AssetPipeline;

$pipeline = app(AssetPipeline::class);

// Offload directory
$result = $pipeline->offload('public/images', [
    'extensions' => ['jpg', 'png', 'gif', 'webp'],
    'min_size' => 1024, // Only files > 1KB
]);

echo "Uploaded: {$result['uploaded']} files\n";
echo "Skipped: {$result['skipped']} files\n";
```

## URL Builder

Advanced URL construction with transformations:

```php
use Core\Cdn\Services\CdnUrlBuilder;

$builder = app(CdnUrlBuilder::class);

$url = $builder->build('images/photo.jpg', [
    // Dimensions
    'width' => 800,
    'height' => 600,
    'aspect_ratio' => '16:9',

    // Quality
    'quality' => 85,
    'format' => 'webp',

    // Effects
    'blur' => 10,
    'brightness' => 1.2,
    'contrast' => 1.1,

    // Cropping
    'crop' => 'center',
    'gravity' => 'face',
]);
```

## BunnyCDN Specific

### Pull Zone Management

```php
use Core\Cdn\Services\BunnyCdnService;

$bunny = app(BunnyCdnService::class);

// Get pull zone info
$pullZone = $bunny->getPullZone($pullZoneId);

// Add/remove hostnames
$bunny->addHostname($pullZoneId, 'cdn.example.com');
$bunny->removeHostname($pullZoneId, 'cdn.example.com');

// Enable/disable cache
$bunny->setCacheEnabled($pullZoneId, true);
```

### Storage Zone Operations

```php
use Core\Cdn\Services\BunnyStorageService;

$storage = app(BunnyStorageService::class);

// List files
$files = $storage->list('media/');

// Get file info
$info = $storage->getFileInfo('media/photo.jpg');

// Download file
$contents = $storage->download('media/photo.jpg');
```

## Cloudflare Specific

### Zone Management

```php
use Core\Cdn\Services\FluxCdnService;

$cloudflare = app(FluxCdnService::class);

// Purge cache by URLs
$cloudflare->purgePaths([
    'https://example.com/images/photo.jpg',
    'https://example.com/styles/app.css',
]);

// Purge by cache tags
$cloudflare->purgeTags(['images', 'media']);

// Purge everything
$cloudflare->purgeEverything();
```

## Testing

### Fake CDN

```php
use Core\Cdn\Facades\Cdn;

class UploadTest extends TestCase
{
    public function test_uploads_file(): void
    {
        Cdn::fake();

        $response = $this->post('/upload', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        Cdn::assertStored('media/photo.jpg');
    }
}
```

### Assert Operations

```php
// Assert file was stored
Cdn::assertStored('path/file.jpg');

// Assert file was deleted
Cdn::assertDeleted('path/file.jpg');

// Assert cache was purged
Cdn::assertPurged('path/file.jpg');

// Assert nothing was stored
Cdn::assertNothingStored();
```

## Performance

### URL Caching

CDN URLs are cached to avoid repeated lookups:

```php
// URLs cached for 1 hour
$url = Cdn::url('images/photo.jpg'); // Generates URL + caches
$url = Cdn::url('images/photo.jpg'); // Returns from cache
```

### Batch Operations

```php
// Batch delete (single API call)
Cdn::delete([
    'media/photo1.jpg',
    'media/photo2.jpg',
    'media/photo3.jpg',
]);

// Batch purge (single API call)
Cdn::purge([
    'images/*.jpg',
    'styles/*.css',
]);
```

## Best Practices

### 1. Use Helper in Blade

```blade
{{-- ✅ Good --}}
<img src="{{ cdn_url('images/photo.jpg') }}" alt="Photo">

{{-- ❌ Bad - relative path --}}
<img src="/images/photo.jpg" alt="Photo">
```

### 2. Offload Static Assets

```php
// ✅ Good - offload after upload
public function store(Request $request)
{
    $path = $request->file('image')->store('media');

    // Offload to CDN immediately
    Cdn::store($path);

    return $path;
}
```

### 3. Purge After Updates

```php
// ✅ Good - purge on update
public function update(Request $request, Media $media)
{
    $oldPath = $media->path;

    $media->update($request->validated());

    // Purge old file from cache
    Cdn::purge($oldPath);
}
```

### 4. Use Transformations

```php
// ✅ Good - CDN transforms image
<img src="{{ cdn_url('photo.jpg', ['width' => 400, 'quality' => 85]) }}">

// ❌ Bad - transform server-side
<img src="{{ route('image.transform', ['path' => 'photo.jpg', 'width' => 400]) }}">
```

## Troubleshooting

### Files Not Appearing

```bash
# Verify CDN credentials
php artisan tinker
>>> Cdn::store(UploadedFile::fake()->image('test.jpg'), 'test')

# Check CDN dashboard for new files
```

### Purge Not Working

```bash
# Verify pull zone ID
php artisan tinker
>>> config('cdn.bunnycdn.pull_zone_id')

# Manual purge via dashboard
```

### URLs Not Resolving

```php
// Check CDN URL configuration
echo config('cdn.bunnycdn.cdn_url');

// Verify file exists on CDN
$exists = Cdn::exists('path/file.jpg');
```

## Learn More

- [Media Processing →](/core/media)
- [Storage Configuration →](/guide/configuration#storage)
- [Asset Pipeline →](/core/media#asset-pipeline)
