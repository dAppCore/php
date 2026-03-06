<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

use Core\Cdn\Services\BunnyCdnService;
use Core\Config\ConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(\Tests\TestCase::class);

function createMockedService(bool $configured = true): BunnyCdnService
{
    $configMock = Mockery::mock(ConfigService::class);
    $configMock->shouldReceive('isConfigured')->with('cdn.bunny')->andReturn($configured);
    $configMock->shouldReceive('get')->with('cdn.bunny.api_key')->andReturn('test-api-key');
    $configMock->shouldReceive('get')->with('cdn.bunny.pull_zone_id')->andReturn('12345');
    $configMock->shouldReceive('get')->with('cdn.bunny.storage.public.api_key')->andReturn('test-storage-key');
    $configMock->shouldReceive('get')->with('cdn.bunny.storage.public.hostname', 'storage.bunnycdn.com')->andReturn('storage.bunnycdn.com');

    return new BunnyCdnService($configMock);
}

describe('BunnyCdnService Configuration', function () {
    it('reports configured when api key and pull zone are set', function () {
        $service = createMockedService(configured: true);

        expect($service->isConfigured())->toBeTrue();
    });

    it('reports not configured when api key is missing', function () {
        $service = createMockedService(configured: false);

        expect($service->isConfigured())->toBeFalse();
    });
});

describe('BunnyCdnService URL Purging', function () {
    it('purges single url successfully', function () {
        Http::fake([
            'api.bunny.net/purge' => Http::response(null, 200),
        ]);

        $service = createMockedService();
        $result = $service->purgeUrls(['https://example.com/page']);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.bunny.net/purge'
                && $request['url'] === 'https://example.com/page';
        });
    });

    it('purges multiple urls', function () {
        Http::fake([
            'api.bunny.net/purge' => Http::response(null, 200),
        ]);

        $service = createMockedService();
        $urls = [
            'https://example.com/page1',
            'https://example.com/page2',
            'https://example.com/page3',
        ];

        $result = $service->purgeUrls($urls);

        expect($result)->toBeTrue();
        Http::assertSentCount(3);
    });

    it('returns true for empty url array', function () {
        Http::fake();

        $service = createMockedService();
        $result = $service->purgeUrls([]);

        expect($result)->toBeTrue();
        Http::assertNothingSent();
    });

    it('returns false when not configured', function () {
        Log::spy();

        $service = createMockedService(configured: false);
        $result = $service->purgeUrls(['https://example.com/page']);

        expect($result)->toBeFalse();
        Log::shouldHaveReceived('warning')->with('BunnyCDN: Cannot purge - not configured');
    });

    it('handles purge failure gracefully', function () {
        Http::fake([
            'api.bunny.net/purge' => Http::response(['error' => 'Not found'], 404),
        ]);
        Log::spy();

        $service = createMockedService();
        $result = $service->purgeUrls(['https://example.com/page']);

        expect($result)->toBeFalse();
        Log::shouldHaveReceived('error');
    });

    it('returns partial success when some urls fail', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(null, 200)
                ->push(['error' => 'Failed'], 500)
                ->push(null, 200),
        ]);

        $service = createMockedService();
        $result = $service->purgeUrls([
            'https://example.com/page1',
            'https://example.com/page2',
            'https://example.com/page3',
        ]);

        expect($result)->toBeFalse();
        Http::assertSentCount(2); // Stops at first failure
    });
});

describe('BunnyCdnService Full Cache Purge', function () {
    it('purges all cache successfully', function () {
        Http::fake([
            'api.bunny.net/pullzone/12345/purgeCache' => Http::response(null, 204),
        ]);

        $service = createMockedService();
        $result = $service->purgeAll();

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'pullzone/12345/purgeCache');
        });
    });

    it('returns false when not configured', function () {
        $service = createMockedService(configured: false);
        $result = $service->purgeAll();

        expect($result)->toBeFalse();
    });

    it('handles purge all failure', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);
        Log::spy();

        $service = createMockedService();
        $result = $service->purgeAll();

        expect($result)->toBeFalse();
    });
});

describe('BunnyCdnService Tag Purging', function () {
    it('purges by tag successfully', function () {
        Http::fake([
            'api.bunny.net/pullzone/12345/purgeCache' => Http::response(null, 204),
        ]);

        $service = createMockedService();
        $result = $service->purgeByTag('workspace:abc123');

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'purgeCache')
                && $request['CacheTag'] === 'workspace:abc123';
        });
    });

    it('returns false when not configured', function () {
        $service = createMockedService(configured: false);
        $result = $service->purgeByTag('test-tag');

        expect($result)->toBeFalse();
    });
});

describe('BunnyCdnService Stats', function () {
    it('gets pull zone stats', function () {
        Http::fake([
            'api.bunny.net/statistics*' => Http::response([
                'TotalBandwidthUsed' => 1000000,
                'CacheHitRate' => 0.95,
                'TotalOriginTraffic' => 50000,
            ], 200),
        ]);

        $service = createMockedService();
        $stats = $service->getStats();

        expect($stats)->toBeArray()
            ->and($stats['TotalBandwidthUsed'])->toBe(1000000)
            ->and($stats['CacheHitRate'])->toBe(0.95);
    });

    it('returns null when not configured', function () {
        $service = createMockedService(configured: false);
        $stats = $service->getStats();

        expect($stats)->toBeNull();
    });

    it('returns null on api failure', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = createMockedService();
        $stats = $service->getStats();

        expect($stats)->toBeNull();
    });
});

describe('BunnyCdnService Storage Operations', function () {
    it('lists storage files', function () {
        Http::fake([
            'storage.bunnycdn.com/my-zone/*' => Http::response([
                ['ObjectName' => 'file1.jpg', 'Length' => 1024],
                ['ObjectName' => 'file2.jpg', 'Length' => 2048],
            ], 200),
        ]);

        $service = createMockedService();
        $files = $service->listStorageFiles('my-zone', '/images/');

        expect($files)->toBeArray()
            ->and($files)->toHaveCount(2);
    });

    it('returns null when not configured', function () {
        $service = createMockedService(configured: false);
        $files = $service->listStorageFiles('my-zone');

        expect($files)->toBeNull();
    });

    it('uploads file to storage', function () {
        Http::fake([
            'storage.bunnycdn.com/*' => Http::response(null, 201),
        ]);

        $service = createMockedService();
        $result = $service->uploadFile('my-zone', 'images/test.jpg', 'file-content');

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'my-zone/images/test.jpg')
                && $request->method() === 'PUT';
        });
    });

    it('returns false on upload failure', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Storage full'], 507),
        ]);

        $service = createMockedService();
        $result = $service->uploadFile('my-zone', 'images/test.jpg', 'content');

        expect($result)->toBeFalse();
    });

    it('deletes file from storage', function () {
        Http::fake([
            'storage.bunnycdn.com/*' => Http::response(null, 200),
        ]);

        $service = createMockedService();
        $result = $service->deleteFile('my-zone', 'images/test.jpg');

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'my-zone/images/test.jpg')
                && $request->method() === 'DELETE';
        });
    });
});
