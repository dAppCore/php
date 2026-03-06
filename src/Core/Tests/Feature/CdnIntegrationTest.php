<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Tests\Feature;

use Core\Cdn\Services\AssetPipeline;
use Core\Cdn\Services\CdnUrlBuilder;
use Core\Cdn\Services\StorageUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CDN Integration Tests.
 *
 * Tests the complete CDN pipeline including:
 * - URL building
 * - Asset storage
 * - CDN delivery
 * - vBucket isolation
 */
#[\PHPUnit\Framework\Attributes\Group('slow')]
class CdnIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected CdnUrlBuilder $urlBuilder;

    protected StorageUrlResolver $urlResolver;

    protected AssetPipeline $assetPipeline;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure CDN URLs
        Config::set('cdn.urls.cdn', 'https://cdn.example.com');
        Config::set('cdn.urls.public', 'https://public.example.com');
        Config::set('cdn.urls.private', 'https://private.example.com');
        Config::set('cdn.urls.apex', 'https://example.com');

        // Configure testing disks
        Config::set('filesystems.disks.hetzner-public', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/cdn-public'),
            'url' => env('APP_URL').'/cdn-public',
            'visibility' => 'public',
        ]);

        Config::set('filesystems.disks.hetzner-private', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/cdn-private'),
            'url' => env('APP_URL').'/cdn-private',
            'visibility' => 'private',
        ]);

        // Initialize services
        $this->urlBuilder = new CdnUrlBuilder;
        $this->urlResolver = new StorageUrlResolver($this->urlBuilder);
        $this->assetPipeline = new AssetPipeline($this->urlResolver);
    }

    protected function tearDown(): void
    {
        // Clean up testing disks
        Storage::disk('hetzner-public')->deleteDirectory('');
        Storage::disk('hetzner-private')->deleteDirectory('');

        parent::tearDown();
    }

    public function test_cdn_url_builder_generates_correct_cdn_url(): void
    {
        $url = $this->urlBuilder->cdn('assets/image.jpg');

        $this->assertEquals('https://cdn.example.com/assets/image.jpg', $url);
    }

    public function test_cdn_url_builder_generates_correct_origin_url(): void
    {
        $url = $this->urlBuilder->origin('assets/image.jpg');

        $this->assertEquals('https://public.example.com/assets/image.jpg', $url);
    }

    public function test_cdn_url_builder_generates_correct_private_url(): void
    {
        $url = $this->urlBuilder->private('secure/document.pdf');

        $this->assertEquals('https://private.example.com/secure/document.pdf', $url);
    }

    public function test_cdn_url_builder_generates_correct_apex_url(): void
    {
        $url = $this->urlBuilder->apex('fallback/asset.css');

        $this->assertEquals('https://example.com/fallback/asset.css', $url);
    }

    public function test_cdn_url_builder_handles_leading_slashes(): void
    {
        $url = $this->urlBuilder->cdn('/assets/image.jpg');

        $this->assertEquals('https://cdn.example.com/assets/image.jpg', $url);
    }

    public function test_cdn_url_builder_handles_trailing_slashes_in_base_url(): void
    {
        $url = $this->urlBuilder->cdn('assets/image.jpg', 'https://cdn.example.com/');

        $this->assertEquals('https://cdn.example.com/assets/image.jpg', $url);
    }

    public function test_url_resolver_returns_correct_cdn_url(): void
    {
        $url = $this->urlResolver->cdn('media/photo.jpg');

        $this->assertStringStartsWith('https://cdn.example.com/', $url);
        $this->assertStringContainsString('media/photo.jpg', $url);
    }

    public function test_url_resolver_returns_correct_origin_url(): void
    {
        $url = $this->urlResolver->origin('media/photo.jpg');

        $this->assertStringStartsWith('https://public.example.com/', $url);
        $this->assertStringContainsString('media/photo.jpg', $url);
    }

    public function test_asset_pipeline_stores_file_to_public_disk(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $result = $this->assetPipeline->store($file, 'media');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('cdn_url', $result);
        $this->assertArrayHasKey('origin_url', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('mime', $result);

        // Verify file exists on disk
        $this->assertTrue(Storage::disk('hetzner-public')->exists($result['path']));
    }

    public function test_asset_pipeline_stores_file_with_custom_filename(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $result = $this->assetPipeline->store($file, 'media', 'custom-name.jpg');

        $this->assertStringContainsString('custom-name.jpg', $result['path']);
    }

    public function test_asset_pipeline_generates_correct_category_paths(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $result = $this->assetPipeline->store($file, 'avatar');

        $this->assertStringStartsWith('avatars/', $result['path']);
    }

    public function test_asset_pipeline_stores_content_string(): void
    {
        $content = 'test file content';

        $result = $this->assetPipeline->storeContents($content, 'media', 'test.txt');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertTrue(Storage::disk('hetzner-public')->exists($result['path']));
        $this->assertEquals($content, Storage::disk('hetzner-public')->get($result['path']));
    }

    public function test_asset_pipeline_stores_private_content(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $result = $this->assetPipeline->storePrivate($file, 'documents');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertTrue(Storage::disk('hetzner-private')->exists($result['path']));
    }

    public function test_asset_pipeline_deletes_file_from_storage(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->assetPipeline->store($file, 'media');

        $deleted = $this->assetPipeline->delete($result['path']);

        $this->assertTrue($deleted);
        $this->assertFalse(Storage::disk('hetzner-public')->exists($result['path']));
    }

    public function test_asset_pipeline_checks_file_existence(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->assetPipeline->store($file, 'media');

        $this->assertTrue($this->assetPipeline->exists($result['path']));
        $this->assertFalse($this->assetPipeline->exists('nonexistent/file.jpg'));
    }

    public function test_asset_pipeline_returns_file_size(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 50); // 50KB
        $result = $this->assetPipeline->store($file, 'media');

        $size = $this->assetPipeline->size($result['path']);

        $this->assertNotNull($size);
        $this->assertGreaterThan(0, $size);
    }

    public function test_asset_pipeline_returns_mime_type(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->assetPipeline->store($file, 'media');

        $mimeType = $this->assetPipeline->mimeType($result['path']);

        $this->assertNotNull($mimeType);
        $this->assertStringContainsString('image', $mimeType);
    }

    public function test_asset_pipeline_copies_between_public_and_private(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $publicResult = $this->assetPipeline->store($file, 'media');

        $privateResult = $this->assetPipeline->copy(
            $publicResult['path'],
            'private/copy.jpg',
            'hetzner-public',
            'hetzner-private'
        );

        $this->assertIsArray($privateResult);
        $this->assertTrue(Storage::disk('hetzner-private')->exists($privateResult['path']));
    }

    public function test_url_builder_with_version_parameter(): void
    {
        $url = $this->urlBuilder->cdn('assets/app.js');
        $versioned = $this->urlBuilder->withVersion($url, 'v123');

        $this->assertStringContainsString('v=v123', $versioned);
    }

    public function test_vbucket_id_generation_is_consistent(): void
    {
        $domain1 = 'example.com';
        $domain2 = 'example.com';
        $domain3 = 'different.com';

        $id1 = $this->urlBuilder->vBucketId($domain1);
        $id2 = $this->urlBuilder->vBucketId($domain2);
        $id3 = $this->urlBuilder->vBucketId($domain3);

        $this->assertEquals($id1, $id2);
        $this->assertNotEquals($id1, $id3);
    }

    public function test_vbucket_path_includes_domain_hash(): void
    {
        $domain = 'workspace.example.com';
        $path = 'assets/image.jpg';

        $vBucketPath = $this->urlBuilder->vBucketPath($domain, $path);

        $this->assertStringContainsString('/', $vBucketPath);
        $this->assertStringEndsWith('assets/image.jpg', $vBucketPath);
    }

    public function test_cdn_url_with_query_parameters(): void
    {
        $url = $this->urlBuilder->cdn('assets/image.jpg?width=100&height=100');

        $this->assertStringContainsString('width=100', $url);
        $this->assertStringContainsString('height=100', $url);
    }

    public function test_signed_url_generation(): void
    {
        Config::set('cdn.signing_key', 'test-secret-key');
        Config::set('cdn.token_lifetime', 3600);

        $url = $this->urlBuilder->signed('private/document.pdf', 3600);

        $this->assertNotNull($url);
        $this->assertStringContainsString('token=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    public function test_url_resolver_returns_public_disk_instance(): void
    {
        $disk = $this->urlResolver->publicDisk();

        $this->assertInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class, $disk);
    }

    public function test_url_resolver_returns_private_disk_instance(): void
    {
        $disk = $this->urlResolver->privateDisk();

        $this->assertInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class, $disk);
    }

    public function test_asset_pipeline_handles_large_files(): void
    {
        $file = UploadedFile::fake()->create('large.pdf', 5000); // 5MB

        $result = $this->assetPipeline->store($file, 'media');

        $this->assertIsArray($result);
        $this->assertTrue(Storage::disk('hetzner-public')->exists($result['path']));
        $this->assertGreaterThan(1000000, $result['size']); // > 1MB
    }

    public function test_asset_pipeline_handles_special_characters_in_filename(): void
    {
        $file = UploadedFile::fake()->image('test file with spaces.jpg');

        $result = $this->assetPipeline->store($file, 'media');

        $this->assertIsArray($result);
        $this->assertTrue(Storage::disk('hetzner-public')->exists($result['path']));
    }

    public function test_asset_pipeline_delete_many_removes_multiple_files(): void
    {
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');

        $result1 = $this->assetPipeline->store($file1, 'media');
        $result2 = $this->assetPipeline->store($file2, 'media');

        $results = $this->assetPipeline->deleteMany([
            $result1['path'],
            $result2['path'],
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[$result1['path']]);
        $this->assertTrue($results[$result2['path']]);
        $this->assertFalse(Storage::disk('hetzner-public')->exists($result1['path']));
        $this->assertFalse(Storage::disk('hetzner-public')->exists($result2['path']));
    }

    public function test_url_builder_handles_empty_path(): void
    {
        $url = $this->urlBuilder->cdn('');

        $this->assertEquals('https://cdn.example.com/', $url);
    }

    public function test_url_resolver_provides_both_cdn_and_origin_urls(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->assetPipeline->store($file, 'media');

        $urls = $this->assetPipeline->urls($result['path']);

        $this->assertIsArray($urls);
        $this->assertArrayHasKey('cdn_url', $urls);
        $this->assertArrayHasKey('origin_url', $urls);
        $this->assertStringStartsWith('https://cdn.example.com/', $urls['cdn_url']);
        $this->assertStringStartsWith('https://public.example.com/', $urls['origin_url']);
    }
}
