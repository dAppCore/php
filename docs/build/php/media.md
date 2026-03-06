# Media Processing

Powerful media processing with image optimization, responsive images, lazy thumbnails, and CDN integration.

## Image Optimization

### Automatic Optimization

Images are automatically optimized on upload:

```php
use Core\Media\Image\ImageOptimizer;

$optimizer = app(ImageOptimizer::class);

// Optimize image
$optimizer->optimize($path);

// Returns optimized path with reduced file size
```

**Optimization Features:**
- Strip EXIF data (privacy)
- Lossless compression
- Format conversion (WebP/AVIF support)
- Quality adjustment
- Dimension constraints

### Configuration

```php
// config/media.php
return [
    'optimization' => [
        'enabled' => true,
        'quality' => 85,
        'max_width' => 2560,
        'max_height' => 2560,
        'strip_exif' => true,
        'convert_to_webp' => true,
    ],
];
```

### Manual Optimization

```php
use Core\Media\Image\ImageOptimization;

$optimization = app(ImageOptimization::class);

// Optimize with custom quality
$optimization->optimize($path, quality: 90);

// Optimize and resize
$optimization->optimize($path, maxWidth: 1920, maxHeight: 1080);

// Get optimization stats
$stats = $optimization->getStats($path);
// ['original_size' => 2500000, 'optimized_size' => 890000, 'savings' => 64]
```

## Responsive Images

### Generating Responsive Images

```php
use Core\Media\Support\ImageResizer;

$resizer = app(ImageResizer::class);

// Generate multiple sizes
$sizes = $resizer->resize($originalPath, [
    'thumbnail' => [150, 150],
    'small' => [320, 240],
    'medium' => [768, 576],
    'large' => [1920, 1440],
]);

// Returns:
[
    'thumbnail' => '/storage/images/photo-150x150.jpg',
    'small' => '/storage/images/photo-320x240.jpg',
    'medium' => '/storage/images/photo-768x576.jpg',
    'large' => '/storage/images/photo-1920x1440.jpg',
]
```

### Responsive Image Tag

```blade
<picture>
    <source
        srcset="{{ cdn($image->large) }} 1920w,
                {{ cdn($image->medium) }} 768w,
                {{ cdn($image->small) }} 320w"
        sizes="(max-width: 768px) 100vw, 50vw"
    >
    <img
        src="{{ cdn($image->medium) }}"
        alt="{{ $image->alt }}"
        loading="lazy"
    >
</picture>
```

### Modern Format Support

```php
use Core\Media\Image\ModernFormatSupport;

$formats = app(ModernFormatSupport::class);

// Check browser support
if ($formats->supportsWebP(request())) {
    return cdn($image->webp);
}

if ($formats->supportsAVIF(request())) {
    return cdn($image->avif);
}

return cdn($image->jpg);
```

**Blade Component:**

```blade
<x-responsive-image
    :image="$post->featured_image"
    sizes="(max-width: 768px) 100vw, 50vw"
    loading="lazy"
/>
```

## Lazy Thumbnails

Generate thumbnails on-demand:

### Configuration

```php
// config/media.php
return [
    'lazy_thumbnails' => [
        'enabled' => true,
        'cache_ttl' => 86400, // 24 hours
        'allowed_sizes' => [
            'thumbnail' => [150, 150],
            'small' => [320, 240],
            'medium' => [768, 576],
            'large' => [1920, 1440],
        ],
    ],
];
```

### Generating Thumbnails

```php
use Core\Media\Thumbnail\LazyThumbnail;

// Generate thumbnail URL (not created until requested)
$url = lazy_thumbnail($originalPath, 'medium');
// Returns: /thumbnail/abc123/medium/photo.jpg

// Generate with custom dimensions
$url = lazy_thumbnail($originalPath, [width: 500, height: 300]);
```

### Thumbnail Controller

Thumbnails are generated on first request:

```
GET /thumbnail/{hash}/{size}/{filename}
```

**Process:**
1. Check if thumbnail exists in cache
2. If not, generate from original
3. Store in cache/CDN
4. Serve to client

**Benefits:**
- No upfront processing
- Storage efficient
- CDN-friendly
- Automatic cleanup

## Media Conversions

Define custom media conversions:

```php
<?php

namespace Mod\Blog\Media;

use Core\Media\Abstracts\MediaConversion;

class PostThumbnailConversion extends MediaConversion
{
    public function name(): string
    {
        return 'post-thumbnail';
    }

    public function apply(string $path): string
    {
        return $this->resize($path, 400, 300)
            ->optimize(quality: 85)
            ->sharpen()
            ->save();
    }
}
```

**Register Conversion:**

```php
use Core\Events\FrameworkBooted;
use Core\Media\Conversions\MediaImageResizerConversion;

public function onFrameworkBooted(FrameworkBooted $event): void
{
    MediaImageResizerConversion::register(
        new PostThumbnailConversion()
    );
}
```

**Apply Conversion:**

```php
use Core\Media\Jobs\ProcessMediaConversion;

// Queue conversion
ProcessMediaConversion::dispatch($media, 'post-thumbnail');

// Synchronous conversion
$converted = $media->convert('post-thumbnail');
```

## EXIF Data

### Stripping EXIF

Remove privacy-sensitive metadata:

```php
use Core\Media\Image\ExifStripper;

$stripper = app(ExifStripper::class);

// Strip all EXIF data
$stripper->strip($imagePath);

// Strip specific tags
$stripper->strip($imagePath, preserve: [
    'orientation', // Keep orientation
    'copyright',   // Keep copyright
]);
```

**Auto-strip on Upload:**

```php
// config/media.php
return [
    'optimization' => [
        'strip_exif' => true, // Default: strip everything
        'preserve_exif' => ['orientation'], // Keep these tags
    ],
];
```

### Reading EXIF

```php
use Intervention\Image\ImageManager;

$manager = app(ImageManager::class);

$image = $manager->read($path);
$exif = $image->exif();

$camera = $exif->get('Model'); // Camera model
$date = $exif->get('DateTimeOriginal'); // Photo date
$gps = $exif->get('GPSLatitude'); // GPS coordinates (privacy risk!)
```

## CDN Integration

### Uploading to CDN

```php
use Core\Cdn\Services\BunnyStorageService;

$cdn = app(BunnyStorageService::class);

// Upload file
$cdnPath = $cdn->upload($localPath, 'images/photo.jpg');

// Upload with public URL
$url = $cdn->uploadAndGetUrl($localPath, 'images/photo.jpg');
```

### CDN Helper

```blade
{{-- Blade template --}}
<img src="{{ cdn('images/photo.jpg') }}" alt="Photo">

{{-- With transformation --}}
<img src="{{ cdn('images/photo.jpg', ['width' => 800, 'quality' => 85]) }}" alt="Photo">
```

### Purging CDN Cache

```php
use Core\Cdn\Services\FluxCdnService;

$cdn = app(FluxCdnService::class);

// Purge single file
$cdn->purge('/images/photo.jpg');

// Purge multiple files
$cdn->purge([
    '/images/photo.jpg',
    '/images/thumbnail.jpg',
]);

// Purge entire directory
$cdn->purge('/images/*');
```

## Progress Tracking

Track conversion progress:

```php
use Core\Media\Events\ConversionProgress;

// Listen for progress
Event::listen(ConversionProgress::class, function ($event) {
    echo "Processing: {$event->percentage}%\n";
    echo "Step: {$event->currentStep}/{$event->totalSteps}\n";
});
```

**With Livewire:**

```php
class MediaUploader extends Component
{
    public $progress = 0;

    protected $listeners = ['conversionProgress' => 'updateProgress'];

    public function updateProgress($percentage)
    {
        $this->progress = $percentage;
    }

    public function render()
    {
        return view('livewire.media-uploader');
    }
}
```

```blade
<div>
    @if($progress > 0)
        <div class="progress-bar">
            <div style="width: {{ $progress }}%"></div>
        </div>
        <p>Processing: {{ $progress }}%</p>
    @endif
</div>
```

## Queued Processing

Process media in background:

```php
use Core\Media\Jobs\GenerateThumbnail;
use Core\Media\Jobs\ProcessMediaConversion;

// Queue thumbnail generation
GenerateThumbnail::dispatch($media, 'large');

// Queue conversion
ProcessMediaConversion::dispatch($media, 'optimized');

// Chain jobs
GenerateThumbnail::dispatch($media, 'large')
    ->chain([
        new ProcessMediaConversion($media, 'watermark'),
        new ProcessMediaConversion($media, 'optimize'),
    ]);
```

## Best Practices

### 1. Optimize on Upload

```php
// ✅ Good - optimize immediately
public function store(Request $request)
{
    $path = $request->file('image')->store('images');

    $optimizer = app(ImageOptimizer::class);
    $optimizer->optimize(storage_path("app/{$path}"));

    return $path;
}

// ❌ Bad - serve unoptimized images
public function store(Request $request)
{
    return $request->file('image')->store('images');
}
```

### 2. Use Lazy Thumbnails

```php
// ✅ Good - generate on-demand
<img src="{{ lazy_thumbnail($image->path, 'medium') }}">

// ❌ Bad - generate all sizes upfront
$resizer->resize($path, [
    'thumbnail' => [150, 150],
    'small' => [320, 240],
    'medium' => [768, 576],
    'large' => [1920, 1440],
    'xlarge' => [2560, 1920],
]); // Slow upload, wasted storage
```

### 3. Strip EXIF Data

```php
// ✅ Good - protect privacy
$stripper->strip($imagePath);

// ❌ Bad - leak GPS coordinates, camera info
// (no stripping)
```

### 4. Use CDN for Assets

```php
// ✅ Good - CDN delivery
<img src="{{ cdn($image->path) }}">

// ❌ Bad - serve from origin
<img src="{{ Storage::url($image->path) }}">
```

## Testing

```php
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Core\Media\Image\ImageOptimizer;

class MediaTest extends TestCase
{
    public function test_optimizes_uploaded_image(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 2000, 2000);

        $path = $file->store('test');
        $fullPath = storage_path("app/{$path}");

        $originalSize = filesize($fullPath);

        $optimizer = app(ImageOptimizer::class);
        $optimizer->optimize($fullPath);

        $optimizedSize = filesize($fullPath);

        $this->assertLessThan($originalSize, $optimizedSize);
    }

    public function test_generates_lazy_thumbnail(): void
    {
        $path = UploadedFile::fake()->image('photo.jpg')->store('test');

        $url = lazy_thumbnail($path, 'medium');

        $this->assertStringContainsString('/thumbnail/', $url);
    }
}
```

## Learn More

- [CDN Integration →](/core/cdn)
- [Configuration →](/core/configuration)
