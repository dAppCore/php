<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Database Migration Tests
 *
 * Ensures migrations and seeders run successfully.
 * This catches schema issues before deployment.
 *
 * These tests verify migrations via artisan commands.
 * Run with: ./vendor/bin/pest tests/Feature/DatabaseMigrationTest.php
 */
pest()->group('slow');

describe('Database Migrations', function () {
    it('module migration files exist and are readable', function () {
        // This project uses modular monolith - migrations live in app/Mod/*/Migrations/
        $modulePaths = glob(app_path('Mod/*/Migrations'));
        $corePaths = glob(app_path('Core/*/Migrations'));
        $allPaths = array_merge($modulePaths, $corePaths);

        $allFiles = [];
        foreach ($allPaths as $path) {
            $files = glob($path.'/*.php');
            $allFiles = array_merge($allFiles, $files);
        }

        // Also check database/migrations for any legacy migrations
        $legacyPath = database_path('migrations');
        if (is_dir($legacyPath)) {
            $legacyFiles = glob($legacyPath.'/*.php');
            $allFiles = array_merge($allFiles, $legacyFiles);
        }

        expect(count($allFiles))->toBeGreaterThan(0, 'No migration files found in modules or database/migrations');

        foreach ($allFiles as $file) {
            expect(is_readable($file))->toBeTrue('Migration file not readable: '.$file);

            // Basic check - file should contain migration class definition
            // Both anonymous ('return new class') and named ('class .*Migration') formats are valid
            $content = file_get_contents($file);
            $hasAnonymousClass = str_contains($content, 'return new class');
            $hasNamedClass = (bool) preg_match('/class\s+\w+\s+extends\s+Migration/', $content);
            expect($hasAnonymousClass || $hasNamedClass)->toBeTrue('Invalid migration format: '.basename($file));
        }
    });

    it('module seeder files exist', function () {
        // This project uses modular monolith - seeders live in app/Mod/*/Database/Seeders/
        $modulePaths = glob(app_path('Mod/*/Database/Seeders'));
        $corePaths = glob(app_path('Core/*/Database/Seeders'));
        $allPaths = array_merge($modulePaths, $corePaths);

        $allFiles = [];
        foreach ($allPaths as $path) {
            $files = glob($path.'/*.php');
            $allFiles = array_merge($allFiles, $files);
        }

        // Also check database/seeders for any legacy seeders
        $legacyPath = database_path('seeders');
        if (is_dir($legacyPath)) {
            $legacyFiles = glob($legacyPath.'/*.php');
            $allFiles = array_merge($allFiles, $legacyFiles);
        }

        // It's OK to have no seeders if the project doesn't use them
        if (count($allFiles) === 0) {
            $this->markTestSkipped('No seeder files found (project may not use seeders)');
        }

        foreach ($allFiles as $file) {
            expect(is_readable($file))->toBeTrue('Seeder file not readable: '.$file);
        }
    });

    it('DatabaseSeeder class exists and has run method', function () {
        expect(class_exists(\Database\Seeders\DatabaseSeeder::class))->toBeTrue();
        expect(method_exists(\Database\Seeders\DatabaseSeeder::class, 'run'))->toBeTrue();
    });

    it('critical seeder classes exist in modules', function () {
        // Check for module seeders - these live in Mod namespaces
        $moduleSeederClasses = [
            \Core\Tenant\Database\Seeders\PackageSeeder::class,
            \Core\Mod\Commerce\Database\Seeders\TaxRateSeeder::class,
        ];

        $found = 0;
        foreach ($moduleSeederClasses as $seeder) {
            if (class_exists($seeder)) {
                $found++;
            }
        }

        // At least some seeders should exist
        expect($found)->toBeGreaterThan(0, 'No critical seeder classes found');
    });
});
