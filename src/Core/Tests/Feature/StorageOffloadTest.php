<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Tests\Feature;

use Core\Cdn\Models\StorageOffload as StorageOffloadModel;
use Core\Cdn\Services\StorageOffload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('slow')]
class StorageOffloadTest extends TestCase
{
    use RefreshDatabase;

    protected StorageOffload $offloadService;

    protected string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable offload for testing
        Config::set('offload.enabled', true);
        Config::set('offload.disk', 'testing');
        Config::set('offload.keep_local', false);
        Config::set('offload.cdn_url', 'https://cdn.example.com');

        // Configure testing disk
        Config::set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/offload'),
            'url' => env('APP_URL').'/testing',
            'visibility' => 'public',
        ]);

        // Rebind the service so it picks up the new config
        $this->app->forgetInstance(StorageOffload::class);
        $this->app->bind(StorageOffload::class, function () {
            return new StorageOffload;
        });

        $this->offloadService = app(StorageOffload::class);

        // Create test file
        $this->testFilePath = storage_path('app/test-file.jpg');
        file_put_contents($this->testFilePath, 'test image content');
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testFilePath)) {
            @unlink($this->testFilePath);
        }

        // Clean up testing disk
        Storage::disk('testing')->deleteDirectory('');

        parent::tearDown();
    }

    public function test_service_is_enabled_when_configured(): void
    {
        $this->assertTrue($this->offloadService->isEnabled());
    }

    public function test_service_is_disabled_when_not_configured(): void
    {
        Config::set('offload.enabled', false);
        $service = new StorageOffload;

        $this->assertFalse($service->isEnabled());
    }

    public function test_can_upload_file_to_remote_storage(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'test');

        $this->assertInstanceOf(StorageOffloadModel::class, $result);
        $this->assertEquals('testing', $result->disk);
        $this->assertEquals($this->testFilePath, $result->local_path);
        $this->assertNotNull($result->remote_path);
        $this->assertGreaterThan(0, $result->file_size);
        $this->assertEquals('test', $result->category);

        // Verify file exists on remote storage
        $this->assertTrue(Storage::disk('testing')->exists($result->remote_path));
    }

    public function test_upload_creates_database_record(): void
    {
        $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertDatabaseHas('storage_offloads', [
            'local_path' => $this->testFilePath,
            'disk' => 'testing',
            'category' => 'media',
        ]);
    }

    public function test_upload_calculates_file_hash(): void
    {
        $originalContent = file_get_contents($this->testFilePath);
        $expectedHash = hash('sha256', $originalContent);

        $result = $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertEquals($expectedHash, $result->hash);
    }

    public function test_upload_stores_mime_type(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertNotEmpty($result->mime_type);
    }

    public function test_upload_with_custom_remote_path(): void
    {
        $customPath = 'custom/path/file.jpg';
        $result = $this->offloadService->upload($this->testFilePath, $customPath, 'media');

        $this->assertEquals($customPath, $result->remote_path);
        $this->assertTrue(Storage::disk('testing')->exists($customPath));
    }

    public function test_upload_with_metadata(): void
    {
        $metadata = [
            'user_id' => 123,
            'original_name' => 'photo.jpg',
        ];

        $result = $this->offloadService->upload($this->testFilePath, null, 'media', $metadata);

        $this->assertEquals(123, $result->getMetadata('user_id'));
        $this->assertEquals('photo.jpg', $result->getMetadata('original_name'));
    }

    public function test_upload_deletes_local_file_when_configured(): void
    {
        Config::set('offload.keep_local', false);

        $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertFileDoesNotExist($this->testFilePath);
    }

    public function test_upload_keeps_local_file_when_configured(): void
    {
        Config::set('offload.keep_local', true);

        // Rebind service to pick up new config
        $service = new StorageOffload;
        $service->upload($this->testFilePath, null, 'media');

        $this->assertFileExists($this->testFilePath);
    }

    public function test_upload_returns_null_when_disabled(): void
    {
        Config::set('offload.enabled', false);
        $service = new StorageOffload;

        $result = $service->upload($this->testFilePath, null, 'media');

        $this->assertNull($result);
    }

    public function test_upload_returns_null_for_missing_file(): void
    {
        $result = $this->offloadService->upload('/nonexistent/file.jpg', null, 'media');

        $this->assertNull($result);
    }

    public function test_upload_respects_max_file_size(): void
    {
        Config::set('offload.max_file_size', 5); // 5 bytes

        // Rebind service to pick up new config
        $service = new StorageOffload;
        $result = $service->upload($this->testFilePath, null, 'media');

        $this->assertNull($result);
    }

    public function test_upload_respects_allowed_extensions(): void
    {
        Config::set('offload.allowed_extensions', ['png', 'gif']);

        // Rebind service to pick up new config
        $service = new StorageOffload;

        // .jpg extension should be rejected
        $result = $service->upload($this->testFilePath, null, 'media');

        $this->assertNull($result);
    }

    public function test_can_delete_offloaded_file(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'media');
        $remotePath = $result->remote_path;

        $deleted = $this->offloadService->delete($this->testFilePath);

        $this->assertTrue($deleted);
        $this->assertFalse(Storage::disk('testing')->exists($remotePath));
        $this->assertDatabaseMissing('storage_offloads', [
            'local_path' => $this->testFilePath,
        ]);
    }

    public function test_delete_returns_false_for_non_offloaded_file(): void
    {
        $deleted = $this->offloadService->delete('/nonexistent/file.jpg');

        $this->assertFalse($deleted);
    }

    public function test_can_get_url_for_offloaded_file(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, 'test/file.jpg', 'media');

        $url = $this->offloadService->url($this->testFilePath);

        $this->assertNotNull($url);
        $this->assertStringContainsString('cdn.example.com', $url);
        $this->assertStringContainsString('test/file.jpg', $url);
    }

    public function test_url_returns_null_for_non_offloaded_file(): void
    {
        $url = $this->offloadService->url('/nonexistent/file.jpg');

        $this->assertNull($url);
    }

    public function test_url_uses_cdn_when_configured(): void
    {
        Config::set('offload.cdn_url', 'https://cdn.example.com');

        $result = $this->offloadService->upload($this->testFilePath, 'test/file.jpg', 'media');
        $url = $this->offloadService->url($this->testFilePath);

        $this->assertStringStartsWith('https://cdn.example.com/', $url);
    }

    public function test_url_uses_disk_url_when_no_cdn_configured(): void
    {
        Config::set('offload.cdn_url', null);

        $result = $this->offloadService->upload($this->testFilePath, 'test/file.jpg', 'media');
        $url = $this->offloadService->url($this->testFilePath);

        $this->assertNotNull($url);
        $this->assertStringContainsString('test/file.jpg', $url);
    }

    public function test_can_check_if_file_is_offloaded(): void
    {
        $this->assertFalse($this->offloadService->isOffloaded($this->testFilePath));

        $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertTrue($this->offloadService->isOffloaded($this->testFilePath));
    }

    public function test_can_get_offload_record(): void
    {
        $uploaded = $this->offloadService->upload($this->testFilePath, null, 'media');

        $record = $this->offloadService->getRecord($this->testFilePath);

        $this->assertInstanceOf(StorageOffloadModel::class, $record);
        $this->assertEquals($uploaded->id, $record->id);
    }

    public function test_get_record_returns_null_for_non_offloaded_file(): void
    {
        $record = $this->offloadService->getRecord('/nonexistent/file.jpg');

        $this->assertNull($record);
    }

    public function test_can_verify_file_integrity(): void
    {
        $this->offloadService->upload($this->testFilePath, null, 'media');

        $valid = $this->offloadService->verifyIntegrity($this->testFilePath);

        $this->assertTrue($valid);
    }

    public function test_integrity_verification_fails_for_corrupted_file(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'media');

        // Corrupt the remote file
        Storage::disk('testing')->put($result->remote_path, 'corrupted content');

        $valid = $this->offloadService->verifyIntegrity($this->testFilePath);

        $this->assertFalse($valid);
    }

    public function test_can_get_storage_statistics(): void
    {
        // Upload a few files
        file_put_contents($this->testFilePath, str_repeat('x', 1024)); // 1KB
        $this->offloadService->upload($this->testFilePath, null, 'media');

        $file2 = storage_path('app/test-file-2.jpg');
        file_put_contents($file2, str_repeat('y', 2048)); // 2KB
        $this->offloadService->upload($file2, null, 'avatar');

        $stats = $this->offloadService->getStats();

        $this->assertEquals(2, $stats['total_files']);
        $this->assertEquals(3072, $stats['total_size']); // 1KB + 2KB
        $this->assertStringContainsString('KB', $stats['total_size_human']);
        $this->assertIsArray($stats['by_category']);
        $this->assertCount(2, $stats['by_category']); // media and avatar

        // Clean up
        @unlink($file2);
    }

    public function test_model_has_human_readable_file_size(): void
    {
        file_put_contents($this->testFilePath, str_repeat('x', 1024)); // 1KB
        $result = $this->offloadService->upload($this->testFilePath, null, 'media');

        $this->assertEquals('1 KB', $result->file_size_human);
    }

    public function test_model_can_detect_image_files(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'media');
        $result->mime_type = 'image/jpeg';
        $result->save();

        $this->assertTrue($result->isImage());
        $this->assertFalse($result->isVideo());
        $this->assertFalse($result->isAudio());
    }

    public function test_model_scopes_work_correctly(): void
    {
        $this->offloadService->upload($this->testFilePath, null, 'media');

        $file2 = storage_path('app/test-file-2.jpg');
        file_put_contents($file2, 'test content');
        $this->offloadService->upload($file2, null, 'avatar');

        $mediaFiles = StorageOffloadModel::inCategory('media')->get();
        $testingDiskFiles = StorageOffloadModel::forDisk('testing')->get();

        $this->assertCount(1, $mediaFiles);
        $this->assertCount(2, $testingDiskFiles);

        // Clean up
        @unlink($file2);
    }

    public function test_url_caching_works(): void
    {
        Config::set('offload.cache.enabled', true);

        $result = $this->offloadService->upload($this->testFilePath, null, 'media');

        // First call - should cache
        $url1 = $this->offloadService->url($this->testFilePath);
        $this->assertNotNull($url1);

        // Second call - should return cached URL (database query not needed)
        $url2 = $this->offloadService->url($this->testFilePath);
        $this->assertEquals($url1, $url2);

        // After deletion, cache should be cleared and URL should be null
        $this->offloadService->delete($this->testFilePath);
        $url3 = $this->offloadService->url($this->testFilePath);

        $this->assertNull($url3);
    }

    public function test_remote_path_generation_organises_by_category(): void
    {
        $result = $this->offloadService->upload($this->testFilePath, null, 'page');

        $this->assertStringStartsWith('pages/', $result->remote_path);
    }

    public function test_can_get_disk_instance(): void
    {
        $disk = $this->offloadService->getDisk();

        $this->assertInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class, $disk);
    }

    public function test_can_get_disk_name(): void
    {
        $diskName = $this->offloadService->getDiskName();

        $this->assertEquals('testing', $diskName);
    }
}
