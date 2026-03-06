<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

pest()->group('slow');

use Core\Media\Image\ImageOptimization;
use Core\Media\Image\ImageOptimizer;
use Core\Media\Image\OptimizationResult;
use Core\Tenant\Models\User;
use Core\Tenant\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->user->hostWorkspaces()->attach($this->workspace->id, ['is_default' => true]);

    // Ensure storage directory exists
    Storage::fake('local');

    // Override config for testing
    config([
        'images.optimization.enabled' => true,
        'images.optimization.driver' => 'gd',
        'images.optimization.quality' => 80,
        'images.optimization.png_compression' => 6,
        'images.optimization.min_size_kb' => 10,
        'images.optimization.max_size_mb' => 10,
    ]);
});

describe('ImageOptimizer service', function () {
    it('can be instantiated', function () {
        $optimizer = new ImageOptimizer;

        expect($optimizer)->toBeInstanceOf(ImageOptimizer::class);
    });

    it('optimises JPEG images with GD driver', function () {
        // Create a test JPEG image
        $testImage = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        expect($result)->toBeInstanceOf(OptimizationResult::class);
        expect($result->originalSize)->toBeGreaterThan(0);
        expect($result->optimizedSize)->toBeGreaterThan(0);
        expect($result->driver)->toBe('gd');
        expect($result->path)->toBe($testImage);

        // Clean up
        unlink($testImage);
    });

    it('optimises PNG images with compression', function () {
        // Create a test PNG image
        $testImage = createTestPngImage(400, 300);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        expect($result)->toBeInstanceOf(OptimizationResult::class);
        expect($result->originalSize)->toBeGreaterThan(0);
        expect($result->optimizedSize)->toBeGreaterThan(0);
        expect($result->driver)->toBe('gd');

        // Clean up
        unlink($testImage);
    });

    it('skips files smaller than minimum size', function () {
        // Create a tiny image (less than 10KB)
        $testImage = createTestJpegImage(50, 50);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        // Should return no-op result (no optimization)
        expect($result->percentageSaved)->toBe(0);

        // Clean up
        unlink($testImage);
    });

    it('skips files larger than maximum size', function () {
        // Set a very small max size for testing
        config(['images.optimization.max_size_mb' => 0.001]); // 1KB

        $testImage = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        // Should return no-op result
        expect($result->percentageSaved)->toBe(0);

        // Clean up
        unlink($testImage);
    });

    it('throws exception for non-existent file', function () {
        $optimizer = new ImageOptimizer;

        $optimizer->optimize('/path/to/nonexistent/file.jpg');
    })->throws(\InvalidArgumentException::class, 'File not found');

    it('handles invalid image files gracefully', function () {
        // Create a text file pretending to be an image
        $testFile = sys_get_temp_dir().'/test_invalid.jpg';
        file_put_contents($testFile, 'This is not an image');

        $optimizer = new ImageOptimizer;

        $result = $optimizer->optimize($testFile);

        // Service should return no-op result instead of throwing
        expect($result)->toBeInstanceOf(OptimizationResult::class);
        expect($result->percentageSaved)->toBe(0);

        unlink($testFile);
    });

    it('respects custom quality settings', function () {
        $testImage1 = createTestJpegImage(800, 600);
        $testImage2 = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;

        // High quality (less compression)
        $result1 = $optimizer->optimize($testImage1, ['quality' => 95]);

        // Low quality (more compression)
        $result2 = $optimizer->optimize($testImage2, ['quality' => 50]);

        // Lower quality should result in smaller file
        expect($result2->optimizedSize)->toBeLessThan($result1->optimizedSize);

        // Clean up
        unlink($testImage1);
        unlink($testImage2);
    });

    it('handles WebP images', function () {
        // Only test if WebP support is available
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support not available in GD');
        }

        $testImage = createTestWebpImage(400, 300);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        expect($result)->toBeInstanceOf(OptimizationResult::class);
        expect($result->driver)->toBe('gd');

        // Clean up
        unlink($testImage);
    });

    it('can be disabled via config', function () {
        config(['images.optimization.enabled' => false]);

        $testImage = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        // Should return no-op result
        expect($result->percentageSaved)->toBe(0);
        expect($result->originalSize)->toBe($result->optimizedSize);

        // Clean up
        unlink($testImage);
    });
});

describe('Uploaded file optimization', function () {
    it('optimises uploaded files', function () {
        // Create a test JPEG
        $tempPath = createTestJpegImage(800, 600);

        // Simulate UploadedFile
        $uploadedFile = new UploadedFile($tempPath, 'test.jpg', 'image/jpeg', null, true);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimizeUploadedFile($uploadedFile);

        expect($result)->toBeInstanceOf(OptimizationResult::class);
        expect($result->originalSize)->toBeGreaterThan(0);

        // Clean up
        @unlink($tempPath);
    });
});

describe('OptimizationResult value object', function () {
    it('calculates bytes saved correctly', function () {
        $result = new OptimizationResult(
            originalSize: 100000,
            optimizedSize: 65000,
            percentageSaved: 35,
            path: '/test/path.jpg',
            driver: 'gd'
        );

        expect($result->bytesSaved())->toBe(35000);
    });

    it('reports success when savings exist', function () {
        $result = new OptimizationResult(
            originalSize: 100000,
            optimizedSize: 80000,
            percentageSaved: 20,
            path: '/test/path.jpg',
            driver: 'gd'
        );

        expect($result->wasSuccessful())->toBeTrue();
    });

    it('reports no success when no savings', function () {
        $result = new OptimizationResult(
            originalSize: 100000,
            optimizedSize: 100000,
            percentageSaved: 0,
            path: '/test/path.jpg',
            driver: 'gd'
        );

        expect($result->wasSuccessful())->toBeFalse();
    });

    it('generates human-readable summary', function () {
        $result = new OptimizationResult(
            originalSize: 120000,
            optimizedSize: 66000,
            percentageSaved: 45,
            path: '/test/path.jpg',
            driver: 'gd'
        );

        $summary = $result->getSummary();

        expect($summary)->toContain('45%');
        expect($summary)->toContain('KB');
    });

    it('converts to array', function () {
        $result = new OptimizationResult(
            originalSize: 100000,
            optimizedSize: 80000,
            percentageSaved: 20,
            path: '/test/path.jpg',
            driver: 'gd'
        );

        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'original_size',
            'optimized_size',
            'percentage_saved',
            'path',
            'driver',
            'bytes_saved',
        ]);
        expect($array['bytes_saved'])->toBe(20000);
    });
});

describe('ImageOptimization model', function () {
    it('stores optimization record in database', function () {
        $optimization = ImageOptimization::create([
            'path' => '/storage/test.jpg',
            'original_path' => null,
            'original_size' => 150000,
            'optimized_size' => 90000,
            'percentage_saved' => 40,
            'driver' => 'gd',
            'quality' => 80,
            'workspace_id' => $this->workspace->id,
        ]);

        expect($optimization->exists)->toBeTrue();
        expect($optimization->workspace_id)->toBe($this->workspace->id);
        expect($optimization->percentage_saved)->toBe(40);
    });

    it('generates human-readable savings', function () {
        $optimization = ImageOptimization::create([
            'path' => '/storage/test.jpg',
            'original_size' => 120000,
            'optimized_size' => 66000,
            'percentage_saved' => 45,
            'driver' => 'gd',
            'quality' => 80,
        ]);

        $savings = $optimization->savings_human;

        expect($savings)->toContain('45%');
        expect($savings)->toContain('KB');
    });

    it('scopes to workspace', function () {
        $workspace2 = Workspace::factory()->create();

        // Create optimization for workspace 1
        ImageOptimization::create([
            'path' => '/storage/test1.jpg',
            'original_size' => 100000,
            'optimized_size' => 80000,
            'percentage_saved' => 20,
            'driver' => 'gd',
            'quality' => 80,
            'workspace_id' => $this->workspace->id,
        ]);

        // Create optimization for workspace 2
        ImageOptimization::create([
            'path' => '/storage/test2.jpg',
            'original_size' => 100000,
            'optimized_size' => 80000,
            'percentage_saved' => 20,
            'driver' => 'gd',
            'quality' => 80,
            'workspace_id' => $workspace2->id,
        ]);

        $workspace1Optimizations = ImageOptimization::forWorkspace($this->workspace)->get();

        expect($workspace1Optimizations)->toHaveCount(1);
        expect($workspace1Optimizations->first()->workspace_id)->toBe($this->workspace->id);
    });

    it('calculates workspace statistics', function () {
        // Create multiple optimizations
        ImageOptimization::create([
            'path' => '/storage/test1.jpg',
            'original_size' => 100000,
            'optimized_size' => 80000,
            'percentage_saved' => 20,
            'driver' => 'gd',
            'quality' => 80,
            'workspace_id' => $this->workspace->id,
        ]);

        ImageOptimization::create([
            'path' => '/storage/test2.jpg',
            'original_size' => 150000,
            'optimized_size' => 90000,
            'percentage_saved' => 40,
            'driver' => 'gd',
            'quality' => 80,
            'workspace_id' => $this->workspace->id,
        ]);

        $stats = ImageOptimization::getWorkspaceStats($this->workspace);

        expect($stats['count'])->toBe(2);
        expect($stats['total_original'])->toBe(250000);
        expect($stats['total_optimized'])->toBe(170000);
        expect($stats['total_saved'])->toBe(80000);
        expect($stats['average_percentage'])->toBeGreaterThan(0);
    });
});

describe('Statistics tracking', function () {
    it('records optimization in database via service', function () {
        $testImage = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);

        // Record the optimization
        $record = $optimizer->recordOptimization(
            $result,
            $this->workspace
        );

        expect($record)->toBeInstanceOf(ImageOptimization::class);
        expect($record->workspace_id)->toBe($this->workspace->id);
        expect($record->driver)->toBe('gd');

        // Clean up
        unlink($testImage);
    });

    it('retrieves workspace stats via service', function () {
        $testImage = createTestJpegImage(800, 600);

        $optimizer = new ImageOptimizer;
        $result = $optimizer->optimize($testImage);
        $optimizer->recordOptimization($result, $this->workspace);

        $stats = $optimizer->getStats($this->workspace);

        expect($stats)->toBeArray();
        expect($stats['count'])->toBe(1);

        // Clean up
        unlink($testImage);
    });
});

// Helper functions

function createTestJpegImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);

    // Fill with some colours to make it realistic
    $bgColor = imagecolorallocate($image, 100, 150, 200);
    imagefill($image, 0, 0, $bgColor);

    // Add some shapes for realistic compression
    $shapeColor = imagecolorallocate($image, 255, 100, 50);
    imagefilledrectangle($image, 50, 50, 150, 150, $shapeColor);
    imagefilledellipse($image, 300, 200, 100, 100, $shapeColor);

    $tempPath = sys_get_temp_dir().'/test_'.uniqid().'.jpg';
    imagejpeg($image, $tempPath, 95); // High quality initially
    imagedestroy($image);

    return $tempPath;
}

function createTestPngImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);

    $bgColor = imagecolorallocate($image, 100, 150, 200);
    imagefill($image, 0, 0, $bgColor);

    $shapeColor = imagecolorallocate($image, 255, 100, 50);
    imagefilledrectangle($image, 20, 20, 80, 80, $shapeColor);

    $tempPath = sys_get_temp_dir().'/test_'.uniqid().'.png';
    imagepng($image, $tempPath, 0); // No compression initially
    imagedestroy($image);

    return $tempPath;
}

function createTestWebpImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);

    $bgColor = imagecolorallocate($image, 100, 150, 200);
    imagefill($image, 0, 0, $bgColor);

    $tempPath = sys_get_temp_dir().'/test_'.uniqid().'.webp';
    imagewebp($image, $tempPath, 95);
    imagedestroy($image);

    return $tempPath;
}
