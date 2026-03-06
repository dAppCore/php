<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Tests\Feature;

use Core\Cdn\Middleware\RewriteOffloadedUrls;
use Core\Cdn\Services\StorageOffload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RewriteOffloadedUrlsTest extends TestCase
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
        Config::set('offload.cdn_url', 'https://cdn.example.com');

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

        // Create and offload test file
        $this->testFilePath = storage_path('app/public/test-image.jpg');
        @mkdir(dirname($this->testFilePath), 0755, true);
        file_put_contents($this->testFilePath, 'test image content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            @unlink($this->testFilePath);
        }

        parent::tearDown();
    }

    public function test_middleware_rewrites_urls_in_json_response(): void
    {
        // Offload the file
        $this->offloadService->upload($this->testFilePath, 'media/test-image.jpg', 'media');

        // Create test route
        Route::get('/api/test-data', function () {
            return response()->json([
                'image_url' => url('/storage/test-image.jpg'),
                'other_data' => 'not affected',
            ]);
        })->middleware(RewriteOffloadedUrls::class);

        $response = $this->get('/api/test-data');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertStringContainsString('cdn.example.com', $data['image_url']);
        $this->assertEquals('not affected', $data['other_data']);
    }

    public function test_middleware_handles_nested_urls(): void
    {
        $this->offloadService->upload($this->testFilePath, 'media/test-image.jpg', 'media');

        Route::get('/api/nested-data', function () {
            return response()->json([
                'user' => [
                    'name' => 'John',
                    'avatar' => url('/storage/test-image.jpg'),
                ],
                'items' => [
                    ['image' => url('/storage/test-image.jpg')],
                ],
            ]);
        })->middleware(RewriteOffloadedUrls::class);

        $response = $this->get('/api/nested-data');
        $data = $response->json();

        $this->assertStringContainsString('cdn.example.com', $data['user']['avatar']);
        $this->assertStringContainsString('cdn.example.com', $data['items'][0]['image']);
    }

    public function test_middleware_does_not_rewrite_non_offloaded_urls(): void
    {
        Route::get('/api/normal-url', function () {
            return response()->json([
                'image_url' => url('/storage/not-offloaded.jpg'),
            ]);
        })->middleware(RewriteOffloadedUrls::class);

        $response = $this->get('/api/normal-url');
        $data = $response->json();

        $this->assertStringContainsString('/storage/not-offloaded.jpg', $data['image_url']);
        $this->assertStringNotContainsString('cdn.example.com', $data['image_url']);
    }

    public function test_middleware_preserves_non_url_strings(): void
    {
        Route::get('/api/mixed-data', function () {
            return response()->json([
                'title' => 'storage is great',
                'count' => 42,
                'active' => true,
            ]);
        })->middleware(RewriteOffloadedUrls::class);

        $response = $this->get('/api/mixed-data');

        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'storage is great',
            'count' => 42,
            'active' => true,
        ]);
    }
}
