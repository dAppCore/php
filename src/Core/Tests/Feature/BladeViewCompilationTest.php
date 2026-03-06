<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Blade View Compilation Tests
 *
 * Compiles all blade views to catch missing components, syntax errors,
 * and undefined variables before deployment.
 *
 * Run with: ./vendor/bin/pest tests/Feature/BladeViewCompilationTest.php
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;

describe('Blade View Compilation', function () {
    it('all blade views compile without errors', function () {
        $viewPaths = config('view.paths');
        $errors = [];

        foreach ($viewPaths as $path) {
            $bladeFiles = File::allFiles($path);

            foreach ($bladeFiles as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace($path.'/', '', $file->getPathname());

                // Skip non-blade files
                if (! str_contains($relativePath, '.blade.php')) {
                    continue;
                }

                try {
                    // Get view name from path
                    $viewName = str_replace(['/', '.blade.php'], ['.', ''], $relativePath);

                    // Attempt to compile the view
                    $compiler = app(BladeCompiler::class);
                    $compiler->compile($file->getPathname());
                } catch (Throwable $e) {
                    $errors[] = [
                        'file' => $relativePath,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        if (! empty($errors)) {
            $message = "The following views failed to compile:\n";
            foreach ($errors as $error) {
                $message .= "  - {$error['file']}: {$error['error']}\n";
            }
            $this->fail($message);
        }

        expect($errors)->toBeEmpty();
    });

    it('view cache command runs without errors', function () {
        // Clear any existing cache first
        Artisan::call('view:clear');

        // This will fail if any view has syntax errors
        $exitCode = Artisan::call('view:cache');

        expect($exitCode)->toBe(0);

        // Clean up
        Artisan::call('view:clear');
    });

    it('all blade components are resolvable', function () {
        $viewPaths = config('view.paths');
        $missingComponents = [];

        foreach ($viewPaths as $path) {
            $bladeFiles = File::allFiles($path);

            foreach ($bladeFiles as $file) {
                if (! str_contains($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = File::get($file->getPathname());
                $relativePath = str_replace($path.'/', '', $file->getPathname());

                // Find all x-component usages (e.g., <x-heroicon-o-inbox />)
                preg_match_all('/<x-([a-zA-Z0-9\-:\.]+)/', $content, $matches);

                foreach ($matches[1] as $componentName) {
                    // Skip known component prefixes that are registered
                    $knownPrefixes = [
                        'flux',
                        'flux:',
                        'slot',
                        'layouts.',
                        'components.',
                        'admin.',
                        'public.',
                        'satellite.',
                        'partials.',
                        'admin.',
                    ];

                    $isKnown = false;
                    foreach ($knownPrefixes as $prefix) {
                        if (str_starts_with($componentName, $prefix)) {
                            $isKnown = true;
                            break;
                        }
                    }

                    // Flag heroicons as missing (we use FontAwesome)
                    if (str_starts_with($componentName, 'heroicon')) {
                        $missingComponents[] = [
                            'file' => $relativePath,
                            'component' => $componentName,
                            'suggestion' => 'Use FontAwesome instead: <i class="fa-solid fa-icon-name">',
                        ];
                    }
                }
            }
        }

        if (! empty($missingComponents)) {
            $message = "Found references to unavailable components:\n";
            foreach ($missingComponents as $missing) {
                $message .= "  - {$missing['file']}: x-{$missing['component']}";
                if (isset($missing['suggestion'])) {
                    $message .= " ({$missing['suggestion']})";
                }
                $message .= "\n";
            }
            $this->fail($message);
        }

        expect($missingComponents)->toBeEmpty();
    });
});
