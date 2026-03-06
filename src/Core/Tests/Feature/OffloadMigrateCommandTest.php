<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Tests\Feature;

use Core\Cdn\Models\StorageOffload as StorageOffloadModel;
use Core\Cdn\Services\StorageOffload as StorageOffloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OffloadMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable offload for testing
        Config::set('offload.enabled', true);
        Config::set('offload.disk', 'testing');
        Config::set('offload.keep_local', false);

        Config::set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/offload'),
            'url' => env('APP_URL').'/testing',
            'visibility' => 'public',
        ]);

        // Rebind the service so it picks up the new config
        $this->app->forgetInstance(StorageOffloadService::class);
        $this->app->bind(StorageOffloadService::class, function () {
            return new StorageOffloadService;
        });

        // Create test directory with files
        $this->testDirectory = storage_path('framework/testing/migrate-test');
        @mkdir($this->testDirectory, 0755, true);

        // Create test files
        file_put_contents($this->testDirectory.'/image1.jpg', 'image content 1');
        file_put_contents($this->testDirectory.'/image2.png', 'image content 2');
        file_put_contents($this->testDirectory.'/document.pdf', 'pdf content');
    }

    protected function tearDown(): void
    {
        // Clean up test directory recursively
        if (is_dir($this->testDirectory)) {
            $this->deleteDirectory($this->testDirectory);
        }

        // Clean up testing disk
        Storage::disk('testing')->deleteDirectory('');

        parent::tearDown();
    }

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = glob($dir.'/*');
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->deleteDirectory($item);
            } else {
                @unlink($item);
            }
        }

        @rmdir($dir);
    }

    public function test_command_fails_when_offload_disabled(): void
    {
        Config::set('offload.enabled', false);

        $this->artisan('offload:migrate', ['path' => $this->testDirectory])
            ->expectsOutput('Storage offload is not enabled in configuration.')
            ->assertExitCode(1);
    }

    public function test_command_fails_for_non_existent_directory(): void
    {
        $this->artisan('offload:migrate', ['path' => '/nonexistent/path'])
            ->assertExitCode(1);
    }

    public function test_command_can_migrate_files_with_dry_run(): void
    {
        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--dry-run' => true,
            '--force' => true,
        ])
            ->expectsOutput('DRY RUN MODE - No files will be offloaded')
            ->assertExitCode(0);

        // Files should still exist locally
        $this->assertFileExists($this->testDirectory.'/image1.jpg');
        $this->assertFileExists($this->testDirectory.'/image2.png');

        // No database records should be created
        $this->assertEquals(0, StorageOffloadModel::count());
    }

    public function test_command_can_migrate_files(): void
    {
        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Files should be offloaded
        $this->assertEquals(3, StorageOffloadModel::count());

        // Files should exist on remote storage
        $records = StorageOffloadModel::all();
        foreach ($records as $record) {
            $this->assertTrue(Storage::disk('testing')->exists($record->remote_path));
        }
    }

    public function test_command_respects_category_option(): void
    {
        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--category' => 'page',
            '--force' => true,
        ])
            ->assertExitCode(0);

        $records = StorageOffloadModel::all();
        foreach ($records as $record) {
            $this->assertEquals('page', $record->category);
        }
    }

    public function test_command_respects_allowed_extensions(): void
    {
        Config::set('offload.allowed_extensions', ['jpg', 'png']);

        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Only jpg and png files should be offloaded (not pdf)
        $this->assertEquals(2, StorageOffloadModel::count());
    }

    public function test_command_respects_max_file_size(): void
    {
        // Create large file
        $largeFile = $this->testDirectory.'/large.jpg';
        file_put_contents($largeFile, str_repeat('x', 10000));

        Config::set('offload.max_file_size', 100); // 100 bytes

        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Large file should not be offloaded
        $largeFileRecord = StorageOffloadModel::where('local_path', $largeFile)->first();
        $this->assertNull($largeFileRecord);
    }

    public function test_command_skips_already_offloaded_files(): void
    {
        // Offload one file first
        $service = app(StorageOffloadService::class);
        $service->upload($this->testDirectory.'/image1.jpg', null, 'media');

        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Should still be 3 total (not 4 with duplicate)
        $this->assertEquals(3, StorageOffloadModel::count());
    }

    public function test_command_with_only_missing_flag(): void
    {
        // Offload one file first
        $service = app(StorageOffloadService::class);
        $service->upload($this->testDirectory.'/image1.jpg', null, 'media');

        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--only-missing' => true,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Should offload the 2 missing files
        $this->assertEquals(3, StorageOffloadModel::count());
    }

    public function test_command_displays_summary_table(): void
    {
        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->expectsTable(
                ['Status', 'Count'],
                [
                    ['Processed', 3],
                    ['Failed', 0],
                    ['Skipped', 0],
                    ['Total', 3],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_command_defaults_to_storage_app_public(): void
    {
        // Create a file in default location
        $defaultPath = storage_path('app/public/test.jpg');
        @mkdir(dirname($defaultPath), 0755, true);
        file_put_contents($defaultPath, 'test content');

        $this->artisan('offload:migrate', ['--force' => true])
            ->assertExitCode(0);

        // Clean up
        @unlink($defaultPath);
    }

    public function test_command_handles_empty_directory(): void
    {
        $emptyDir = storage_path('framework/testing/empty-dir');
        @mkdir($emptyDir, 0755, true);

        $this->artisan('offload:migrate', [
            'path' => $emptyDir,
            '--force' => true,
        ])
            ->expectsOutput('No eligible files found.')
            ->assertExitCode(0);

        @rmdir($emptyDir);
    }

    public function test_command_scans_subdirectories(): void
    {
        $subDir = $this->testDirectory.'/subdir';
        @mkdir($subDir, 0755, true);
        file_put_contents($subDir.'/nested.jpg', 'nested content');

        $this->artisan('offload:migrate', [
            'path' => $this->testDirectory,
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Should find 4 files (3 in root + 1 in subdir)
        $this->assertEquals(4, StorageOffloadModel::count());
    }
}
