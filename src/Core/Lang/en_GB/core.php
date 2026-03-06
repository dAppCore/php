<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Core framework translations (en_GB).
 *
 * This file contains all branding and UI text for the Core PHP framework.
 * Override these values to customise your application's branding.
 *
 * Usage in Blade: {{ __('core::core.brand.name') }}
 * Usage in PHP:   __('core::core.brand.name')
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Brand Identity
    |--------------------------------------------------------------------------
    |
    | Your application's name and tagline. Used in page titles, headers,
    | footers, SEO metadata, and throughout the UI.
    |
    */
    'brand' => [
        'name' => 'Core PHP',
        'tagline' => 'Modular Monolith Framework',
        'description' => 'A modern PHP framework for building scalable applications.',
        'copyright' => 'Core PHP Framework',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation & Actions
    |--------------------------------------------------------------------------
    */
    'nav' => [
        'home' => 'Home',
        'features' => 'Features',
        'pricing' => 'Pricing',
        'blog' => 'Blog',
        'help' => 'Help Centre',
        'contact' => 'Contact',
        'login' => 'Login',
        'register' => 'Register',
        'dashboard' => 'Dashboard',
        'manage' => 'Manage',
        'get_started' => 'Get Started',
        'early_access' => 'Get early access',
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer
    |--------------------------------------------------------------------------
    */
    'footer' => [
        'company' => 'Company',
        'about' => 'About Us',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Service',
        'support' => 'Support',
        'faq' => 'FAQ',
        'status' => 'System Status',
        'legal' => 'Legal',
        'powered_by' => 'Powered by :name',
        'part_of' => ':name is part of :parent',
        'all_rights' => 'All rights reserved.',
        'gdpr' => 'GDPR Compliant',
        'eu_hosted' => 'EU Hosted',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Pages
    |--------------------------------------------------------------------------
    */
    'errors' => [
        '404' => [
            'title' => 'Page Not Found',
            'heading' => 'Looks like we took a wrong turn',
            'message' => "The page you're looking for doesn't exist or has been moved. Let's get you back on track.",
            'back_home' => 'Back to home',
            'help' => 'Help centre',
        ],
        '500' => [
            'title' => 'Server Error',
            'heading' => 'Something went wrong on our end',
            'message' => "We're already looking into it. Give it a moment and try again, or head back home while we sort things out.",
            'try_again' => 'Try again',
            'back_home' => 'Back to home',
        ],
        '503' => [
            'title' => 'Under Maintenance',
            'heading' => "We're making improvements",
            'message' => "We're busy with some upgrades. We'll be back shortly.",
            'check_status' => 'Check status',
            'auto_refresh' => 'This page will refresh automatically.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO & Meta
    |--------------------------------------------------------------------------
    */
    'seo' => [
        'default_title' => ':name - :tagline',
        'page_title' => ':title - :name',
        'og_site_name' => 'Core PHP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'create' => 'Create',
        'update' => 'Update',
        'submit' => 'Submit',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'next' => 'Next',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'loading' => 'Loading...',
        'sign_in' => 'Sign in',
        'sign_out' => 'Sign out',
    ],

    /*
    |--------------------------------------------------------------------------
    | Time & Dates
    |--------------------------------------------------------------------------
    */
    'time' => [
        'never' => 'Never',
        'just_now' => 'Just now',
        'ago' => ':time ago',
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'hours' => '{1} :count hour|[2,*] :count hours',
        'days' => '{1} :count day|[2,*] :count days',
        'weeks' => '{1} :count week|[2,*] :count weeks',
        'months' => '{1} :count month|[2,*] :count months',
        'years' => '{1} :count year|[2,*] :count years',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Messages
    |--------------------------------------------------------------------------
    */
    'status' => [
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Info',
        'pending' => 'Pending',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty States
    |--------------------------------------------------------------------------
    */
    'empty' => [
        'no_results' => 'No results found.',
        'no_items' => 'No items yet.',
        'get_started' => 'Get started by creating your first item.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Countable Items (with pluralisation)
    |--------------------------------------------------------------------------
    |
    | These translations support Laravel's pluralisation syntax.
    | Usage: trans_choice('core::core.items.user', $count)
    |
    */
    'items' => [
        'user' => '{0} no users|{1} :count user|[2,*] :count users',
        'item' => '{0} no items|{1} :count item|[2,*] :count items',
        'result' => '{0} no results|{1} :count result|[2,*] :count results',
        'file' => '{0} no files|{1} :count file|[2,*] :count files',
        'page' => '{0} no pages|{1} :count page|[2,*] :count pages',
        'comment' => '{0} no comments|{1} :count comment|[2,*] :count comments',
        'notification' => '{0} no notifications|{1} :count notification|[2,*] :count notifications',
        'message' => '{0} no messages|{1} :count message|[2,*] :count messages',
        'error' => '{0} no errors|{1} :count error|[2,*] :count errors',
        'warning' => '{0} no warnings|{1} :count warning|[2,*] :count warnings',
        'record' => '{0} no records|{1} :count record|[2,*] :count records',
        'entry' => '{0} no entries|{1} :count entry|[2,*] :count entries',
        'task' => '{0} no tasks|{1} :count task|[2,*] :count tasks',
        'issue' => '{0} no issues|{1} :count issue|[2,*] :count issues',
        'change' => '{0} no changes|{1} :count change|[2,*] :count changes',
        'selected' => '{0} none selected|{1} :count selected|[2,*] :count selected',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'required' => 'This field is required.',
        'email' => 'Please enter a valid email address.',
        'min' => 'Must be at least :min characters.',
        'max' => 'Must be no more than :max characters.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Installer
    |--------------------------------------------------------------------------
    */
    'installer' => [
        'title' => 'Core PHP Framework Installer',
        'welcome' => 'Welcome to the Core PHP Framework installer.',
        'env_exists' => '.env file exists',
        'env_created' => 'Created .env file',
        'env_missing' => '.env.example not found',
        'config_saved' => 'Configuration saved',
        'default_config' => 'Using default configuration',
        'migrations_complete' => 'Migrations complete',
        'key_generated' => 'Application key generated',
        'key_exists' => 'Application key exists',
        'storage_link_created' => 'Storage link created',
        'storage_link_exists' => 'Storage link exists',
        'complete' => 'Installation complete!',
        'next_steps' => 'Next steps',
        'prompts' => [
            'app_name' => 'Application name',
            'domain' => 'Base domain (for Valet)',
            'db_driver' => 'Database driver',
            'db_host' => 'Database host',
            'db_port' => 'Database port',
            'db_name' => 'Database name',
            'db_user' => 'Database user',
            'db_password' => 'Database password',
            'run_migrations' => 'Run database migrations?',
        ],
    ],
];
