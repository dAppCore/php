<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Admin Component Tests
 *
 * Tests for the admin UI pattern library components.
 * These components provide data-driven, reusable UI elements for Hades admin pages.
 */

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;

uses()->group('admin-components');

describe('Admin Manager Table Component', function () {
    it('renders table with columns and rows', function () {
        $columns = ['Name', 'Email', 'Status'];
        $rows = [
            ['John Doe', 'john@example.com', 'Active'],
            ['Jane Smith', 'jane@example.com', 'Pending'],
        ];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('Name')
            ->toContain('Email')
            ->toContain('Status')
            ->toContain('John Doe')
            ->toContain('john@example.com')
            ->toContain('Jane Smith');
    });

    it('renders empty state when no rows', function () {
        $columns = ['Name', 'Email'];
        $rows = [];
        $empty = 'No users found.';

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" :empty="$empty" />', [
            'columns' => $columns,
            'rows' => $rows,
            'empty' => $empty,
        ]);

        expect($html)->toContain('No users found.');
    });

    it('renders bold cell type', function () {
        $columns = ['Name'];
        $rows = [
            [['bold' => 'Important Name']],
        ];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('Important Name')
            ->toContain('font-medium');
    });

    it('renders badge cell type', function () {
        $columns = ['Status'];
        $rows = [
            [['badge' => 'Active', 'color' => 'green']],
        ];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('Active')
            ->toContain('rounded-full');
    });

    it('renders multi-line cells', function () {
        $columns = ['User'];
        $rows = [
            [['lines' => [
                ['bold' => 'John Doe'],
                ['muted' => 'john@example.com'],
            ]]],
        ];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('John Doe')
            ->toContain('john@example.com');
    });

    it('renders action buttons', function () {
        $columns = ['Name', 'Actions'];
        $rows = [
            [
                'Test Item',
                ['actions' => [
                    ['icon' => 'pencil', 'click' => 'edit(1)', 'title' => 'Edit'],
                    ['icon' => 'trash', 'click' => 'delete(1)', 'confirm' => 'Are you sure?'],
                ]],
            ],
        ];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('wire:click="edit(1)"')
            ->toContain('wire:click="delete(1)"')
            ->toContain('wire:confirm="Are you sure?"');
    });

    it('supports column alignment', function () {
        $columns = [
            'Name',
            ['label' => 'Count', 'align' => 'right'],
            ['label' => 'Actions', 'align' => 'center'],
        ];
        $rows = [['Test', '10', 'Action']];

        $html = Blade::render('<admin:manager-table :columns="$columns" :rows="$rows" />', [
            'columns' => $columns,
            'rows' => $rows,
        ]);

        expect($html)
            ->toContain('text-right')
            ->toContain('text-center');
    });
});

describe('Admin Flash Component', function () {
    it('renders success message from session', function () {
        session()->flash('message', 'Operation successful!');

        $html = Blade::render('<admin:flash />');

        expect($html)
            ->toContain('Operation successful!')
            ->toContain('bg-green-100');
    });

    it('renders error message from session', function () {
        session()->flash('error', 'Something went wrong!');

        $html = Blade::render('<admin:flash />');

        expect($html)
            ->toContain('Something went wrong!')
            ->toContain('bg-red-100');
    });

    it('renders nothing when no session messages', function () {
        session()->forget(['message', 'error']);

        $html = Blade::render('<admin:flash />');

        expect(trim($html))->toBe('');
    });

    it('supports custom session keys', function () {
        session()->flash('custom_key', 'Custom message');

        $html = Blade::render('<admin:flash key="custom_key" />');

        expect($html)->toContain('Custom message');
    });
});

describe('Admin Filter Bar Component', function () {
    it('renders with default 4 columns', function () {
        $html = Blade::render('<admin:filter-bar><div>Filter content</div></admin:filter-bar>');

        expect($html)
            ->toContain('sm:grid-cols-4')
            ->toContain('Filter content');
    });

    it('renders with custom column count', function () {
        $html = Blade::render('<admin:filter-bar cols="3"><div>Content</div></admin:filter-bar>');

        expect($html)->toContain('sm:grid-cols-3');
    });

    it('renders with 2 columns', function () {
        $html = Blade::render('<admin:filter-bar cols="2"><div>Content</div></admin:filter-bar>');

        expect($html)->toContain('sm:grid-cols-2');
    });

    it('renders with 5 columns', function () {
        $html = Blade::render('<admin:filter-bar cols="5"><div>Content</div></admin:filter-bar>');

        expect($html)->toContain('sm:grid-cols-5');
    });
});

describe('Admin Search Component', function () {
    it('renders search input', function () {
        $html = Blade::render('<admin:search />');

        expect($html)
            ->toContain('type="text"')
            ->toContain('magnifying-glass');
    });

    it('renders with custom placeholder', function () {
        $html = Blade::render('<admin:search placeholder="Search users..." />');

        expect($html)->toContain('placeholder="Search users..."');
    });

    it('renders with wire:model binding', function () {
        $html = Blade::render('<admin:search model="searchTerm" />');

        expect($html)->toContain('wire:model.live.debounce.300ms="searchTerm"');
    });
});

describe('Admin Filter Component', function () {
    it('renders select with placeholder', function () {
        $html = Blade::render('<admin:filter placeholder="All Items" />');

        expect($html)
            ->toContain('<select')
            ->toContain('All Items');
    });

    it('renders with array options', function () {
        $options = [
            'active' => 'Active',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
        ];

        $html = Blade::render('<admin:filter :options="$options" />', [
            'options' => $options,
        ]);

        expect($html)
            ->toContain('value="active"')
            ->toContain('Active')
            ->toContain('value="pending"')
            ->toContain('Pending');
    });

    it('renders with collection options', function () {
        $options = collect([
            (object) ['id' => 1, 'name' => 'Option 1'],
            (object) ['id' => 2, 'name' => 'Option 2'],
        ]);

        $html = Blade::render('<admin:filter :options="$options" />', [
            'options' => $options,
        ]);

        expect($html)
            ->toContain('value="1"')
            ->toContain('Option 1')
            ->toContain('value="2"')
            ->toContain('Option 2');
    });

    it('renders with wire:model binding', function () {
        $html = Blade::render('<admin:filter model="statusFilter" />');

        expect($html)->toContain('wire:model.live="statusFilter"');
    });

    it('renders with label', function () {
        $html = Blade::render('<admin:filter label="Status" />');

        expect($html)
            ->toContain('Status')
            ->toContain('All Statuss'); // Auto-pluralised placeholder
    });
});

describe('Admin Component Integration', function () {
    it('all admin blade components compile without errors', function () {
        $componentsPath = base_path('app/Core/Front/Admin/Blade/components');
        $files = glob("{$componentsPath}/*.blade.php");

        expect($files)->not->toBeEmpty('No admin components found');

        $errors = [];
        foreach ($files as $file) {
            try {
                $compiler = app(\Illuminate\View\Compilers\BladeCompiler::class);
                $compiler->compile($file);
            } catch (Throwable $e) {
                $errors[] = [
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (! empty($errors)) {
            $message = "Admin components failed to compile:\n";
            foreach ($errors as $error) {
                $message .= "  - {$error['file']}: {$error['error']}\n";
            }
            $this->fail($message);
        }

        expect($errors)->toBeEmpty();
    });

    it('admin components are registered with correct namespace', function () {
        // Check that admin: prefix resolves
        $viewFactory = app('view');

        // The admin: namespace should be registered
        $namespaces = $viewFactory->getFinder()->getHints();

        expect($namespaces)->toHaveKey('admin');
    });
});
