<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Core Component Library Tests
 *
 * Verifies all <core:*> components compile, render, and forward props correctly.
 * These thin wrappers delegate to Flux components, isolating Livewire dependencies.
 *
 * Run with: php artisan test app/Core/Tests/Feature/CoreComponentsTest.php
 */

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;

uses()->group('core', 'components');

describe('Core Detection Helpers', function () {

    it('detects Flux Pro installation', function () {
        // Flux Pro is installed in this project
        expect(\Core\Pro::hasFluxPro())->toBeTrue();
    });

    it('identifies Flux Pro components', function () {
        expect(\Core\Pro::requiresFluxPro('calendar'))->toBeTrue();
        expect(\Core\Pro::requiresFluxPro('editor'))->toBeTrue();
        expect(\Core\Pro::requiresFluxPro('chart'))->toBeTrue();
        expect(\Core\Pro::requiresFluxPro('chart.line'))->toBeTrue(); // Nested component
        expect(\Core\Pro::requiresFluxPro('core:kanban'))->toBeTrue(); // With prefix

        // Free components
        expect(\Core\Pro::requiresFluxPro('button'))->toBeFalse();
        expect(\Core\Pro::requiresFluxPro('input'))->toBeFalse();
        expect(\Core\Pro::requiresFluxPro('modal'))->toBeFalse();
    });

    it('respects FontAwesome Pro config', function () {
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => false]);
        expect(\Core\Pro::hasFontAwesomePro())->toBeFalse();

        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => true]);
        expect(\Core\Pro::hasFontAwesomePro())->toBeTrue();

        // Clean up
        \Core\Pro::clearCache();
    });

    it('provides correct FA styles based on Pro/Free', function () {
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => false]);

        $freeStyles = \Core\Pro::fontAwesomeStyles();
        expect($freeStyles)->toContain('solid');
        expect($freeStyles)->toContain('regular');
        expect($freeStyles)->toContain('brands');
        expect($freeStyles)->not->toContain('jelly');
        expect($freeStyles)->not->toContain('light');

        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => true]);

        $proStyles = \Core\Pro::fontAwesomeStyles();
        expect($proStyles)->toContain('jelly');
        expect($proStyles)->toContain('light');
        expect($proStyles)->toContain('thin');

        // Clean up
        \Core\Pro::clearCache();
    });
});

describe('Core Component Library', function () {

    it('has all expected component files', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');

        $expectedComponents = [
            // Foundation
            'button.blade.php',
            'text.blade.php',
            'heading.blade.php',
            'subheading.blade.php',
            'icon.blade.php',
            'card.blade.php',
            'badge.blade.php',
            'input.blade.php',
            'textarea.blade.php',
            'switch.blade.php',

            // Forms
            'select.blade.php',
            'select/option.blade.php',
            'checkbox.blade.php',
            'checkbox/group.blade.php',
            'radio.blade.php',
            'radio/group.blade.php',
            'label.blade.php',
            'field.blade.php',
            'error.blade.php',
            'description.blade.php',

            // Navigation
            'dropdown.blade.php',
            'menu.blade.php',
            'menu/item.blade.php',
            'menu/separator.blade.php',
            'navbar.blade.php',
            'navbar/item.blade.php',
            'navlist.blade.php',
            'navlist/item.blade.php',
            'navlist/group.blade.php',
            'tabs.blade.php',
            'tab.blade.php',

            // Data Display
            'table.blade.php',
            'table/columns.blade.php',
            'table/column.blade.php',
            'table/rows.blade.php',
            'table/row.blade.php',
            'table/cell.blade.php',
            'avatar.blade.php',
            'separator.blade.php',

            // Overlays
            'modal.blade.php',
            'callout.blade.php',
            'callout/heading.blade.php',
            'callout/text.blade.php',
            'popover.blade.php',
            'tooltip.blade.php',
            'accordion.blade.php',
            'accordion/item.blade.php',
            'accordion/heading.blade.php',
            'accordion/content.blade.php',

            // Pro - Inputs
            'autocomplete.blade.php',
            'autocomplete/item.blade.php',
            'slider.blade.php',
            'slider/tick.blade.php',
            'pillbox.blade.php',
            'pillbox/option.blade.php',

            // Pro - Date/Time
            'calendar.blade.php',
            'date-picker.blade.php',
            'date-picker/input.blade.php',
            'date-picker/button.blade.php',
            'time-picker.blade.php',

            // Pro - Rich Content
            'editor.blade.php',
            'editor/toolbar.blade.php',
            'editor/button.blade.php',
            'editor/content.blade.php',
            'composer.blade.php',
            'file-upload.blade.php',
            'file-upload/dropzone.blade.php',
            'file-item.blade.php',
            'file-item/remove.blade.php',

            // Pro - Visualisation
            'chart.blade.php',
            'chart/svg.blade.php',
            'chart/line.blade.php',
            'chart/area.blade.php',
            'chart/point.blade.php',
            'chart/axis.blade.php',
            'chart/cursor.blade.php',
            'chart/tooltip.blade.php',
            'chart/legend.blade.php',
            'chart/summary.blade.php',
            'chart/viewport.blade.php',
            'kanban.blade.php',
            'kanban/column.blade.php',
            'kanban/card.blade.php',

            // Pro - Command
            'command.blade.php',
            'command/input.blade.php',
            'command/items.blade.php',
            'command/item.blade.php',
            'context.blade.php',
        ];

        $missing = [];
        foreach ($expectedComponents as $component) {
            $path = $basePath.'/'.$component;
            if (! File::exists($path)) {
                $missing[] = $component;
            }
        }

        expect($missing)->toBeEmpty(
            'Missing components: '.implode(', ', $missing)
        );
    });

    it('all core blade components compile without errors', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');
        $errors = [];

        $bladeFiles = File::allFiles($basePath);

        foreach ($bladeFiles as $file) {
            if (! str_contains($file->getFilename(), '.blade.php')) {
                continue;
            }

            try {
                $compiler = app(BladeCompiler::class);
                $compiler->compile($file->getPathname());
            } catch (Throwable $e) {
                $relativePath = str_replace($basePath.'/', '', $file->getPathname());
                $errors[] = "{$relativePath}: {$e->getMessage()}";
            }
        }

        expect($errors)->toBeEmpty(
            "Components failed to compile:\n".implode("\n", $errors)
        );
    });

    it('components delegate to flux with attribute forwarding', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');
        $missingAttributeForwarding = [];

        $bladeFiles = File::allFiles($basePath);

        foreach ($bladeFiles as $file) {
            if (! str_contains($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = File::get($file->getPathname());
            $relativePath = str_replace($basePath.'/', '', $file->getPathname());

            // Skip directories with different patterns
            $skipPrefixes = ['layout', 'forms/', 'examples/', 'errors/', 'components/', 'web/'];
            $shouldSkip = false;
            foreach ($skipPrefixes as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) {
                continue;
            }

            // Check if it delegates to flux
            if (preg_match('/<flux:[a-z\.\-]+/', $content)) {
                // Should have attribute forwarding (various patterns)
                $hasForwarding = str_contains($content, '{{ $attributes }}')
                    || str_contains($content, '$attributes->except')
                    || str_contains($content, '$attributes->merge')
                    || str_contains($content, ':$attributes');

                if (! $hasForwarding) {
                    $missingAttributeForwarding[] = $relativePath;
                }
            }
        }

        expect($missingAttributeForwarding)->toBeEmpty(
            "Components missing attribute forwarding:\n".implode("\n", $missingAttributeForwarding)
        );
    });

    it('components with slots forward {{ $slot }}', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');
        $missingSlotForwarding = [];

        $bladeFiles = File::allFiles($basePath);

        foreach ($bladeFiles as $file) {
            if (! str_contains($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = File::get($file->getPathname());
            $relativePath = str_replace($basePath.'/', '', $file->getPathname());

            // Skip directories with different patterns
            $skipPrefixes = ['layout', 'forms/', 'examples/', 'errors/', 'components/', 'web/'];
            $shouldSkip = false;
            foreach ($skipPrefixes as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) {
                continue;
            }

            // If it has opening and closing flux tags, should have {{ $slot }}
            if (preg_match('/<flux:[a-z\.\-]+[^\/]*>.*<\/flux:/s', $content)) {
                if (! str_contains($content, '{{ $slot }}')) {
                    $missingSlotForwarding[] = $relativePath;
                }
            }
        }

        expect($missingSlotForwarding)->toBeEmpty(
            "Components missing {{ \$slot }} forwarding:\n".implode("\n", $missingSlotForwarding)
        );
    });
});

describe('Core Component Rendering', function () {

    it('text matches flux:text', function () {
        $core = Blade::render('<core:text>Hello World</core:text>');
        $flux = Blade::render('<flux:text>Hello World</flux:text>');

        expect($core)->toBe($flux);
    });

    it('button matches flux:button', function () {
        $core = Blade::render('<core:button variant="primary">Click Me</core:button>');
        $flux = Blade::render('<flux:button variant="primary">Click Me</flux:button>');

        expect($core)->toBe($flux);
    });

    it('heading matches flux:heading', function () {
        $core = Blade::render('<core:heading level="2">Title</core:heading>');
        $flux = Blade::render('<flux:heading level="2">Title</flux:heading>');

        expect($core)->toBe($flux);
    });

    it('input matches flux:input', function () {
        $core = Blade::render('<core:input type="email" placeholder="you@example.com" />');
        $flux = Blade::render('<flux:input type="email" placeholder="you@example.com" />');

        expect($core)->toBe($flux);
    });

    it('select matches flux:select', function () {
        $core = Blade::render('
            <core:select>
                <core:select.option value="active">Active</core:select.option>
                <core:select.option value="inactive">Inactive</core:select.option>
            </core:select>
        ');
        $flux = Blade::render('
            <flux:select>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        ');

        expect($core)->toBe($flux);
    });

    it('checkbox.group matches flux:checkbox.group', function () {
        $core = Blade::render('
            <core:checkbox.group>
                <core:checkbox value="opt1" />
                <core:checkbox value="opt2" />
            </core:checkbox.group>
        ');
        $flux = Blade::render('
            <flux:checkbox.group>
                <flux:checkbox value="opt1" />
                <flux:checkbox value="opt2" />
            </flux:checkbox.group>
        ');

        expect($core)->toBe($flux);
    });

    it('table matches flux:table', function () {
        $core = Blade::render('
            <core:table>
                <core:table.columns>
                    <core:table.column>Name</core:table.column>
                    <core:table.column>Email</core:table.column>
                </core:table.columns>
                <core:table.rows>
                    <core:table.row>
                        <core:table.cell>John</core:table.cell>
                        <core:table.cell>john@example.com</core:table.cell>
                    </core:table.row>
                </core:table.rows>
            </core:table>
        ');
        $flux = Blade::render('
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Email</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell>John</flux:table.cell>
                        <flux:table.cell>john@example.com</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        ');

        expect($core)->toBe($flux);
    });

    it('menu matches flux:menu', function () {
        $core = Blade::render('
            <core:menu>
                <core:menu.item>Dashboard</core:menu.item>
                <core:menu.separator />
                <core:menu.item>Settings</core:menu.item>
            </core:menu>
        ');
        $flux = Blade::render('
            <flux:menu>
                <flux:menu.item>Dashboard</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item>Settings</flux:menu.item>
            </flux:menu>
        ');

        expect($core)->toBe($flux);
    });

    it('modal matches flux:modal', function () {
        $core = Blade::render('
            <core:modal name="confirm-delete">
                <p>Are you sure?</p>
            </core:modal>
        ');
        $flux = Blade::render('
            <flux:modal name="confirm-delete">
                <p>Are you sure?</p>
            </flux:modal>
        ');

        expect($core)->toBe($flux);
    });

    it('callout matches flux:callout', function () {
        $core = Blade::render('
            <core:callout variant="warning">
                <core:callout.heading>Warning</core:callout.heading>
                <core:callout.text>This action cannot be undone.</core:callout.text>
            </core:callout>
        ');
        $flux = Blade::render('
            <flux:callout variant="warning">
                <flux:callout.heading>Warning</flux:callout.heading>
                <flux:callout.text>This action cannot be undone.</flux:callout.text>
            </flux:callout>
        ');

        expect($core)->toBe($flux);
    });

    it('accordion matches flux:accordion', function () {
        $core = Blade::render('
            <core:accordion>
                <core:accordion.item>
                    <core:accordion.heading>FAQ 1</core:accordion.heading>
                    <core:accordion.content>Answer 1</core:accordion.content>
                </core:accordion.item>
            </core:accordion>
        ');
        $flux = Blade::render('
            <flux:accordion>
                <flux:accordion.item>
                    <flux:accordion.heading>FAQ 1</flux:accordion.heading>
                    <flux:accordion.content>Answer 1</flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>
        ');

        expect($core)->toBe($flux);
    });
});

describe('Core Pro Component Rendering', function () {

    it('autocomplete matches flux:autocomplete', function () {
        $core = Blade::render('
            <core:autocomplete placeholder="Search...">
                <core:autocomplete.item>Option 1</core:autocomplete.item>
            </core:autocomplete>
        ');
        $flux = Blade::render('
            <flux:autocomplete placeholder="Search...">
                <flux:autocomplete.item>Option 1</flux:autocomplete.item>
            </flux:autocomplete>
        ');

        expect($core)->toBe($flux);
    });

    it('slider matches flux:slider', function () {
        $core = Blade::render('<core:slider min="0" max="100" />');
        $flux = Blade::render('<flux:slider min="0" max="100" />');

        expect($core)->toBe($flux);
    });

    it('calendar matches flux:calendar', function () {
        $core = Blade::render('<core:calendar />');
        $flux = Blade::render('<flux:calendar />');

        expect($core)->toBe($flux);
    });

    it('date-picker matches flux:date-picker', function () {
        $core = Blade::render('<core:date-picker placeholder="Select date" />');
        $flux = Blade::render('<flux:date-picker placeholder="Select date" />');

        expect($core)->toBe($flux);
    });

    it('time-picker matches flux:time-picker', function () {
        $core = Blade::render('<core:time-picker placeholder="Select time" />');
        $flux = Blade::render('<flux:time-picker placeholder="Select time" />');

        expect($core)->toBe($flux);
    });

    it('editor matches flux:editor', function () {
        $core = Blade::render('<core:editor placeholder="Write here..." />');
        $flux = Blade::render('<flux:editor placeholder="Write here..." />');

        expect($core)->toBe($flux);
    });

    it('composer matches flux:composer', function () {
        $core = Blade::render('<core:composer placeholder="Type a message..." />');
        $flux = Blade::render('<flux:composer placeholder="Type a message..." />');

        expect($core)->toBe($flux);
    });

    it('file-upload matches flux:file-upload', function () {
        $core = Blade::render('
            <core:file-upload>
                <core:file-upload.dropzone />
            </core:file-upload>
        ');
        $flux = Blade::render('
            <flux:file-upload>
                <flux:file-upload.dropzone />
            </flux:file-upload>
        ');

        expect($core)->toBe($flux);
    });

    it('chart matches flux:chart', function () {
        $core = Blade::render('
            <core:chart class="h-64">
                <core:chart.svg>
                    <core:chart.line field="value" />
                </core:chart.svg>
            </core:chart>
        ');
        $flux = Blade::render('
            <flux:chart class="h-64">
                <flux:chart.svg>
                    <flux:chart.line field="value" />
                </flux:chart.svg>
            </flux:chart>
        ');

        expect($core)->toBe($flux);
    });

    it('kanban matches flux:kanban', function () {
        $core = Blade::render('
            <core:kanban>
                <core:kanban.column>
                    <core:kanban.column.cards>
                        <core:kanban.card>Task content</core:kanban.card>
                    </core:kanban.column.cards>
                </core:kanban.column>
            </core:kanban>
        ');
        $flux = Blade::render('
            <flux:kanban>
                <flux:kanban.column>
                    <flux:kanban.column.cards>
                        <flux:kanban.card>Task content</flux:kanban.card>
                    </flux:kanban.column.cards>
                </flux:kanban.column>
            </flux:kanban>
        ');

        expect($core)->toBe($flux);
    });

    it('command matches flux:command', function () {
        $core = Blade::render('
            <core:command>
                <core:command.input placeholder="Search..." />
                <core:command.items>
                    <core:command.item>Go Home</core:command.item>
                </core:command.items>
            </core:command>
        ');
        $flux = Blade::render('
            <flux:command>
                <flux:command.input placeholder="Search..." />
                <flux:command.items>
                    <flux:command.item>Go Home</flux:command.item>
                </flux:command.items>
            </flux:command>
        ');

        expect($core)->toBe($flux);
    });

    it('context matches flux:context', function () {
        $core = Blade::render('
            <core:context>
                <div>Right-click me</div>
                <core:menu>
                    <core:menu.item>Action</core:menu.item>
                </core:menu>
            </core:context>
        ');
        $flux = Blade::render('
            <flux:context>
                <div>Right-click me</div>
                <flux:menu>
                    <flux:menu.item>Action</flux:menu.item>
                </flux:menu>
            </flux:context>
        ');

        expect($core)->toBe($flux);
    });

    it('pillbox matches flux:pillbox', function () {
        $core = Blade::render('
            <core:pillbox multiple>
                <core:pillbox.option value="php">PHP</core:pillbox.option>
                <core:pillbox.option value="js">JavaScript</core:pillbox.option>
            </core:pillbox>
        ');
        $flux = Blade::render('
            <flux:pillbox multiple>
                <flux:pillbox.option value="php">PHP</flux:pillbox.option>
                <flux:pillbox.option value="js">JavaScript</flux:pillbox.option>
            </flux:pillbox>
        ');

        expect($core)->toBe($flux);
    });
});

describe('Core PHP Builders', function () {

    it('Button builder renders HTML', function () {
        $button = \Core\Front\Components\Button::make()
            ->label('Save')
            ->primary();  // Use the actual API method

        $html = $button->toHtml();

        expect($html)
            ->toContain('Save')
            ->toContain('button');
    });

    it('Card builder renders with title and body', function () {
        $card = \Core\Front\Components\Card::make()
            ->title('Card Title')
            ->body('Card content goes here');

        $html = $card->toHtml();

        expect($html)
            ->toContain('Card Title')
            ->toContain('Card content goes here');
    });

    it('Heading builder renders with level', function () {
        $heading = \Core\Front\Components\Heading::make()
            ->text('Page Title')
            ->level(1);

        $html = $heading->toHtml();

        expect($html)
            ->toContain('Page Title')
            ->toContain('<h1');
    });

    it('Text builder renders with variant', function () {
        $text = \Core\Front\Components\Text::make()
            ->content('Some text')
            ->muted();

        $html = $text->toHtml();

        expect($html)->toContain('Some text');
    });

    it('NavList builder renders items', function () {
        // NavList::item() signature is: label, href, active, icon
        $navlist = \Core\Front\Components\NavList::make()
            ->item('Dashboard', '/dashboard', false, 'home')
            ->item('Settings', '/settings', false, 'cog');

        $html = $navlist->toHtml();

        expect($html)
            ->toContain('Dashboard')
            ->toContain('Settings')
            ->toContain('/dashboard')
            ->toContain('/settings');
    });

    it('Layout builder renders HLCRF structure', function () {
        // Layout uses short method names: h(), c(), f()
        $layout = \Core\Front\Components\Layout::make('HCF')
            ->h('Header Content')
            ->c('Main Content')
            ->f('Footer Content');

        $html = $layout->toHtml();

        expect($html)
            ->toContain('Header Content')
            ->toContain('Main Content')
            ->toContain('Footer Content')
            ->toContain('data-layout=');  // Layout uses data-layout attribute
    });
});

describe('Component Count Verification', function () {

    it('has at least 100 core components', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');
        $bladeFiles = File::allFiles($basePath);

        $componentCount = collect($bladeFiles)
            ->filter(fn ($file) => str_contains($file->getFilename(), '.blade.php'))
            ->count();

        expect($componentCount)->toBeGreaterThanOrEqual(100);
    });

    it('covers all major Flux component categories', function () {
        $basePath = app_path('Core/Front/Components/View/Blade');

        $categories = [
            'button.blade.php' => 'Foundation',
            'input.blade.php' => 'Forms',
            'select.blade.php' => 'Forms',
            'table.blade.php' => 'Data Display',
            'menu.blade.php' => 'Navigation',
            'modal.blade.php' => 'Overlays',
            'chart.blade.php' => 'Pro Visualisation',
            'editor.blade.php' => 'Pro Rich Content',
            'calendar.blade.php' => 'Pro Date/Time',
            'command.blade.php' => 'Pro Command',
            'kanban.blade.php' => 'Pro Kanban',
        ];

        $missing = [];
        foreach ($categories as $file => $category) {
            if (! File::exists($basePath.'/'.$file)) {
                $missing[] = "{$category} ({$file})";
            }
        }

        expect($missing)->toBeEmpty(
            'Missing category coverage: '.implode(', ', $missing)
        );
    });
});

describe('Custom Components (Non-Flux)', function () {

    it('core:icon renders FontAwesome icons', function () {
        // icon is intentionally custom - uses FontAwesome, not Flux
        $html = Blade::render('<core:icon name="home" />');

        expect($html)
            ->toContain('<i')
            ->toContain('fa-home')
            ->toContain('aria-hidden="true"');
    });

    it('core:icon detects brand icons automatically', function () {
        $html = Blade::render('<core:icon name="github" />');

        expect($html)->toContain('fa-brands');
    });

    it('core:icon falls back to solid for jelly when FA Free', function () {
        // Without FA Pro config, jelly icons should fall back to solid
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => false]);

        $html = Blade::render('<core:icon name="globe" />');

        expect($html)->toContain('fa-solid'); // Jelly → Solid fallback
    });

    it('core:icon uses jelly style when FA Pro enabled', function () {
        // With FA Pro config, jelly icons should use fa-jelly
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => true]);

        $html = Blade::render('<core:icon name="globe" />');

        expect($html)->toContain('fa-jelly');

        // Clean up
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => false]);
    });

    it('core:icon respects explicit style override', function () {
        $html = Blade::render('<core:icon name="globe" style="solid" />');

        expect($html)
            ->toContain('fa-solid')
            ->not->toContain('fa-jelly');
    });

    it('core:icon falls back pro-only styles to free equivalents', function () {
        \Core\Pro::clearCache();
        config(['core.fontawesome.pro' => false]);

        // Light → Regular
        $html = Blade::render('<core:icon name="star" style="light" />');
        expect($html)->toContain('fa-regular');

        // Thin → Regular
        $html = Blade::render('<core:icon name="star" style="thin" />');
        expect($html)->toContain('fa-regular');

        // Duotone → Solid
        $html = Blade::render('<core:icon name="star" style="duotone" />');
        expect($html)->toContain('fa-solid');
    });
});

describe('Comprehensive Core=Flux Parity', function () {

    it('simple self-closing components match flux', function () {
        $components = [
            '<core:badge>Active</core:badge>' => '<flux:badge>Active</flux:badge>',
            '<core:separator />' => '<flux:separator />',
            '<core:avatar src="/avatar.jpg" />' => '<flux:avatar src="/avatar.jpg" />',
            // Note: <core:icon> is custom FontAwesome implementation, not a Flux wrapper
            '<core:subheading>Sub</core:subheading>' => '<flux:subheading>Sub</flux:subheading>',
            '<core:text size="sm">Small</core:text>' => '<flux:text size="sm">Small</flux:text>',
            '<core:textarea placeholder="Write..." />' => '<flux:textarea placeholder="Write..." />',
            '<core:switch />' => '<flux:switch />',
            '<core:label>Name</core:label>' => '<flux:label>Name</flux:label>',
            '<core:description>Help text</core:description>' => '<flux:description>Help text</flux:description>',
            // Note: <core:error> requires $errors variable from Livewire context, tested separately
        ];

        $failures = [];
        foreach ($components as $core => $flux) {
            $coreHtml = Blade::render($core);
            $fluxHtml = Blade::render($flux);
            if ($coreHtml !== $fluxHtml) {
                $failures[] = $core;
            }
        }

        expect($failures)->toBeEmpty(
            'Components not matching flux: '.implode(', ', $failures)
        );
    });

    it('form components with props match flux', function () {
        $components = [
            '<core:input type="password" viewable />' => '<flux:input type="password" viewable />',
            '<core:input clearable placeholder="Search" />' => '<flux:input clearable placeholder="Search" />',
            '<core:select searchable><core:select.option value="a">A</core:select.option></core:select>' => '<flux:select searchable><flux:select.option value="a">A</flux:select.option></flux:select>',
            '<core:checkbox value="yes" />' => '<flux:checkbox value="yes" />',
            '<core:radio value="opt1" />' => '<flux:radio value="opt1" />',
        ];

        $failures = [];
        foreach ($components as $core => $flux) {
            $coreHtml = Blade::render($core);
            $fluxHtml = Blade::render($flux);
            if ($coreHtml !== $fluxHtml) {
                $failures[] = $core;
            }
        }

        expect($failures)->toBeEmpty(
            'Form components not matching flux: '.implode(', ', $failures)
        );
    });

    it('navigation components match flux', function () {
        $components = [
            '<core:dropdown><core:button>Open</core:button><core:menu><core:menu.item>Item</core:menu.item></core:menu></core:dropdown>' => '<flux:dropdown><flux:button>Open</flux:button><flux:menu><flux:menu.item>Item</flux:menu.item></flux:menu></flux:dropdown>',
            '<core:tabs><core:tab name="one">One</core:tab><core:tab name="two">Two</core:tab></core:tabs>' => '<flux:tabs><flux:tab name="one">One</flux:tab><flux:tab name="two">Two</flux:tab></flux:tabs>',
            '<core:navbar><core:navbar.item>Home</core:navbar.item></core:navbar>' => '<flux:navbar><flux:navbar.item>Home</flux:navbar.item></flux:navbar>',
        ];

        $failures = [];
        foreach ($components as $core => $flux) {
            $coreHtml = Blade::render($core);
            $fluxHtml = Blade::render($flux);
            if ($coreHtml !== $fluxHtml) {
                $failures[] = $core;
            }
        }

        expect($failures)->toBeEmpty(
            'Navigation components not matching flux: '.implode(', ', $failures)
        );
    });
});
