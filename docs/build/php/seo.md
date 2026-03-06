# SEO Tools

Comprehensive SEO tools including metadata management, sitemap generation, structured data, and OG image generation.

## SEO Metadata

### Basic Usage

```php
use Core\Seo\SeoMetadata;

$seo = app(SeoMetadata::class);

// Set page metadata
$seo->title('Complete Laravel Tutorial')
    ->description('Learn Laravel from scratch with this comprehensive tutorial')
    ->keywords(['laravel', 'php', 'tutorial', 'web development'])
    ->canonical(url()->current());
```

### Blade Output

```blade
<!DOCTYPE html>
<html>
<head>
    {!! $seo->render() !!}
</head>
</html>
```

**Rendered Output:**

```html
<title>Complete Laravel Tutorial</title>
<meta name="description" content="Learn Laravel from scratch...">
<meta name="keywords" content="laravel, php, tutorial, web development">
<link rel="canonical" href="https://example.com/tutorials/laravel">
```

### Open Graph Tags

```php
$seo->og([
    'title' => 'Complete Laravel Tutorial',
    'description' => 'Learn Laravel from scratch...',
    'image' => cdn('images/laravel-tutorial.jpg'),
    'type' => 'article',
    'url' => url()->current(),
]);
```

**Rendered:**

```html
<meta property="og:title" content="Complete Laravel Tutorial">
<meta property="og:description" content="Learn Laravel from scratch...">
<meta property="og:image" content="https://cdn.example.com/images/laravel-tutorial.jpg">
<meta property="og:type" content="article">
<meta property="og:url" content="https://example.com/tutorials/laravel">
```

### Twitter Cards

```php
$seo->twitter([
    'card' => 'summary_large_image',
    'site' => '@yourhandle',
    'creator' => '@authorhandle',
    'title' => 'Complete Laravel Tutorial',
    'description' => 'Learn Laravel from scratch...',
    'image' => cdn('images/laravel-tutorial.jpg'),
]);
```

## Dynamic OG Images

Generate OG images on-the-fly:

```php
use Core\Seo\Jobs\GenerateOgImageJob;

// Queue image generation
GenerateOgImageJob::dispatch($post, [
    'title' => $post->title,
    'subtitle' => $post->category->name,
    'author' => $post->author->name,
    'template' => 'blog-post',
]);

// Use generated image
$seo->og([
    'image' => $post->og_image_url,
]);
```

### OG Image Templates

```php
// config/seo.php
return [
    'og_images' => [
        'templates' => [
            'blog-post' => [
                'width' => 1200,
                'height' => 630,
                'background' => '#1e293b',
                'title_color' => '#ffffff',
                'title_size' => 64,
                'subtitle_color' => '#94a3b8',
                'subtitle_size' => 32,
            ],
            'product' => [
                'width' => 1200,
                'height' => 630,
                'background' => '#0f172a',
                'overlay' => true,
            ],
        ],
    ],
];
```

### Validating OG Images

```php
use Core\Seo\Validation\OgImageValidator;

$validator = app(OgImageValidator::class);

// Validate image meets requirements
$result = $validator->validate($imagePath);

if (!$result->valid) {
    foreach ($result->errors as $error) {
        echo $error; // "Image width must be at least 1200px"
    }
}
```

**Requirements:**
- Minimum 1200×630px (recommended)
- Maximum 8MB file size
- Supported formats: JPG, PNG, WebP
- Aspect ratio: 1.91:1

## Sitemaps

### Generating Sitemaps

```php
use Core\Seo\Controllers\SitemapController;

// Auto-generated route: /sitemap.xml
// Lists all public URLs

// Custom sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
```

### Adding URLs

```php
namespace Mod\Blog;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        // Posts automatically included in sitemap
        $event->sitemap(function ($sitemap) {
            Post::where('status', 'published')
                ->each(function ($post) use ($sitemap) {
                    $sitemap->add(
                        url: route('blog.show', $post),
                        lastmod: $post->updated_at,
                        changefreq: 'weekly',
                        priority: 0.8
                    );
                });
        });
    }
}
```

### Sitemap Index

For large sites:

```xml
<!-- /sitemap.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap-posts.xml</loc>
        <lastmod>2026-01-26T12:00:00+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap-products.xml</loc>
        <lastmod>2026-01-25T10:30:00+00:00</lastmod>
    </sitemap>
</sitemapindex>
```

## Structured Data

### JSON-LD Schema

```php
$seo->schema([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->title,
    'description' => $post->excerpt,
    'image' => cdn($post->featured_image),
    'datePublished' => $post->published_at->toIso8601String(),
    'dateModified' => $post->updated_at->toIso8601String(),
    'author' => [
        '@type' => 'Person',
        'name' => $post->author->name,
    ],
]);
```

**Rendered:**

```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Complete Laravel Tutorial",
    "description": "Learn Laravel from scratch...",
    "image": "https://cdn.example.com/images/laravel-tutorial.jpg",
    "datePublished": "2026-01-26T12:00:00Z",
    "dateModified": "2026-01-26T14:30:00Z",
    "author": {
        "@type": "Person",
        "name": "John Doe"
    }
}
</script>
```

### Common Schema Types

**Blog Post:**

```php
$seo->schema([
    '@type' => 'BlogPosting',
    'headline' => $post->title,
    'image' => cdn($post->image),
    'author' => ['@type' => 'Person', 'name' => $author->name],
    'publisher' => [
        '@type' => 'Organization',
        'name' => config('app.name'),
        'logo' => cdn('logo.png'),
    ],
]);
```

**Product:**

```php
$seo->schema([
    '@type' => 'Product',
    'name' => $product->name,
    'image' => cdn($product->image),
    'description' => $product->description,
    'sku' => $product->sku,
    'offers' => [
        '@type' => 'Offer',
        'price' => $product->price,
        'priceCurrency' => 'GBP',
        'availability' => 'https://schema.org/InStock',
    ],
]);
```

**Breadcrumbs:**

```php
$seo->schema([
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => route('home'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Blog',
            'item' => route('blog.index'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $post->title,
            'item' => route('blog.show', $post),
        ],
    ],
]);
```

### Testing Structured Data

```bash
php artisan seo:test-structured-data
```

**Or programmatically:**

```php
use Core\Seo\Validation\StructuredDataTester;

$tester = app(StructuredDataTester::class);

$result = $tester->test($jsonLd);

if (!$result->valid) {
    foreach ($result->errors as $error) {
        echo $error; // "Missing required property: datePublished"
    }
}
```

## Canonical URLs

### Setting Canonical

```php
// Explicit canonical
$seo->canonical('https://example.com/blog/laravel-tutorial');

// Auto-detect
$seo->canonical(url()->current());

// Remove query parameters
$seo->canonical(url()->current(), stripQuery: true);
```

### Auditing Canonicals

```bash
php artisan seo:audit-canonical
```

**Checks for:**
- Missing canonical tags
- Self-referencing issues
- HTTPS/HTTP mismatches
- Duplicate content

**Example Output:**

```
Canonical URL Audit
===================

✓ 1,234 pages have canonical tags
✗ 45 pages missing canonical tags
✗ 12 pages with incorrect HTTPS
⚠ 8 pages with duplicate content

Issues:
- /blog/post-1 missing canonical
- /shop/product-5 using HTTP instead of HTTPS
```

## SEO Scoring

Track SEO quality over time:

```php
use Core\Seo\Analytics\SeoScoreTrend;

$trend = app(SeoScoreTrend::class);

// Record current SEO score
$trend->record($post, [
    'title_length' => strlen($post->title),
    'has_meta_description' => !empty($post->meta_description),
    'has_og_image' => !empty($post->og_image),
    'has_canonical' => !empty($post->canonical_url),
    'structured_data' => !empty($post->schema),
]);

// View trends
$scores = $trend->history($post, days: 30);
```

### SEO Score Calculation

```php
// config/seo.php
return [
    'scoring' => [
        'title_length' => ['min' => 30, 'max' => 60, 'points' => 10],
        'meta_description' => ['min' => 120, 'max' => 160, 'points' => 10],
        'has_og_image' => ['points' => 15],
        'has_canonical' => ['points' => 10],
        'has_structured_data' => ['points' => 15],
        'image_alt_text' => ['points' => 10],
        'heading_hierarchy' => ['points' => 10],
        'internal_links' => ['min' => 3, 'points' => 10],
        'external_links' => ['min' => 1, 'points' => 5],
        'word_count' => ['min' => 300, 'points' => 15],
    ],
];
```

## Best Practices

### 1. Always Set Metadata

```php
// ✅ Good - complete metadata
$seo->title('Laravel Tutorial')
    ->description('Learn Laravel...')
    ->canonical(url()->current())
    ->og(['image' => cdn('image.jpg')]);

// ❌ Bad - missing metadata
$seo->title('Laravel Tutorial');
```

### 2. Use Unique Titles & Descriptions

```php
// ✅ Good - unique per page
$seo->title($post->title . ' - Blog')
    ->description($post->excerpt);

// ❌ Bad - same title everywhere
$seo->title(config('app.name'));
```

### 3. Generate OG Images

```php
// ✅ Good - custom OG image
GenerateOgImageJob::dispatch($post);

// ❌ Bad - generic logo
$seo->og(['image' => cdn('logo.png')]);
```

### 4. Validate Structured Data

```bash
# Test before deploying
php artisan seo:test-structured-data

# Check with Google Rich Results Test
# https://search.google.com/test/rich-results
```

## Testing

```php
use Tests\TestCase;
use Core\Seo\SeoMetadata;

class SeoTest extends TestCase
{
    public function test_renders_metadata(): void
    {
        $seo = app(SeoMetadata::class);

        $seo->title('Test Page')
            ->description('Test description');

        $html = $seo->render();

        $this->assertStringContainsString('<title>Test Page</title>', $html);
        $this->assertStringContainsString('name="description"', $html);
    }

    public function test_generates_og_image(): void
    {
        $post = Post::factory()->create();

        GenerateOgImageJob::dispatch($post);

        $this->assertNotNull($post->fresh()->og_image_url);
        $this->assertFileExists(storage_path("app/og-images/{$post->id}.jpg"));
    }
}
```

## Learn More

- [Configuration →](/core/configuration)
- [Media Processing →](/core/media)
