<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers\Livewire;

use Illuminate\Support\Facades\Config;
use Livewire\Component;

/**
 * Livewire component for managing security header configuration.
 *
 * Provides a UI for configuring:
 * - Content Security Policy (CSP) directives
 * - Strict Transport Security (HSTS) settings
 * - Permissions Policy features
 * - Other security headers
 *
 * Settings can be saved to the database (via ConfigService) or exported
 * for inclusion in environment files.
 *
 * ## Usage
 *
 * In a Blade view:
 * ```blade
 * <livewire:header-configuration-manager />
 * ```
 *
 * Or with initial settings:
 * ```blade
 * <livewire:header-configuration-manager :workspace="$workspace" />
 * ```
 */
class HeaderConfigurationManager extends Component
{
    /**
     * Whether security headers are globally enabled.
     */
    public bool $headersEnabled = true;

    /**
     * HSTS configuration.
     */
    public bool $hstsEnabled = true;

    public int $hstsMaxAge = 31536000;

    public bool $hstsIncludeSubdomains = true;

    public bool $hstsPreload = true;

    /**
     * CSP configuration.
     */
    public bool $cspEnabled = true;

    public bool $cspReportOnly = false;

    public ?string $cspReportUri = null;

    public bool $cspNonceEnabled = true;

    /**
     * CSP Directives.
     */
    public array $cspDirectives = [];

    /**
     * External service toggles.
     */
    public bool $jsdelivrEnabled = false;

    public bool $unpkgEnabled = false;

    public bool $googleAnalyticsEnabled = false;

    public bool $facebookEnabled = false;

    /**
     * Permissions Policy features.
     */
    public array $permissionsFeatures = [];

    /**
     * Other security headers.
     */
    public string $xFrameOptions = 'SAMEORIGIN';

    public string $referrerPolicy = 'strict-origin-when-cross-origin';

    /**
     * UI state.
     */
    public string $activeTab = 'csp';

    public bool $showAdvanced = false;

    public ?string $saveMessage = null;

    public ?string $errorMessage = null;

    /**
     * Available CSP directive options.
     */
    protected array $availableDirectives = [
        'default-src',
        'script-src',
        'style-src',
        'img-src',
        'font-src',
        'connect-src',
        'frame-src',
        'frame-ancestors',
        'base-uri',
        'form-action',
        'object-src',
        'media-src',
        'worker-src',
        'manifest-src',
    ];

    /**
     * Mount the component with current configuration.
     */
    public function mount(): void
    {
        $this->loadConfiguration();
    }

    /**
     * Load configuration from current settings.
     */
    public function loadConfiguration(): void
    {
        // Global
        $this->headersEnabled = (bool) config('headers.enabled', true);

        // HSTS
        $this->hstsEnabled = (bool) config('headers.hsts.enabled', true);
        $this->hstsMaxAge = (int) config('headers.hsts.max_age', 31536000);
        $this->hstsIncludeSubdomains = (bool) config('headers.hsts.include_subdomains', true);
        $this->hstsPreload = (bool) config('headers.hsts.preload', true);

        // CSP
        $this->cspEnabled = (bool) config('headers.csp.enabled', true);
        $this->cspReportOnly = (bool) config('headers.csp.report_only', false);
        $this->cspReportUri = config('headers.csp.report_uri');
        $this->cspNonceEnabled = (bool) config('headers.csp.nonce_enabled', true);

        // CSP Directives
        $this->cspDirectives = $this->formatDirectivesForUI(
            config('headers.csp.directives', [])
        );

        // External services
        $this->jsdelivrEnabled = (bool) config('headers.csp.external.jsdelivr.enabled', false);
        $this->unpkgEnabled = (bool) config('headers.csp.external.unpkg.enabled', false);
        $this->googleAnalyticsEnabled = (bool) config('headers.csp.external.google_analytics.enabled', false);
        $this->facebookEnabled = (bool) config('headers.csp.external.facebook.enabled', false);

        // Permissions Policy
        $this->permissionsFeatures = $this->formatPermissionsForUI(
            config('headers.permissions.features', [])
        );

        // Other headers
        $this->xFrameOptions = config('headers.x_frame_options', 'SAMEORIGIN');
        $this->referrerPolicy = config('headers.referrer_policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Format CSP directives for UI display.
     *
     * @param  array<string, array<string>>  $directives
     * @return array<string, string>
     */
    protected function formatDirectivesForUI(array $directives): array
    {
        $formatted = [];
        foreach ($directives as $directive => $sources) {
            $formatted[$directive] = implode(' ', $sources);
        }

        return $formatted;
    }

    /**
     * Format permissions policy for UI display.
     *
     * @param  array<string, array<string>>  $features
     * @return array<string, array{enabled: bool, allowlist: string}>
     */
    protected function formatPermissionsForUI(array $features): array
    {
        $formatted = [];
        foreach ($features as $feature => $allowlist) {
            $formatted[$feature] = [
                'enabled' => ! empty($allowlist),
                'allowlist' => implode(' ', $allowlist),
            ];
        }

        return $formatted;
    }

    /**
     * Update a CSP directive value.
     */
    public function updateDirective(string $directive, string $value): void
    {
        $this->cspDirectives[$directive] = $value;
    }

    /**
     * Add a new CSP directive.
     */
    public function addDirective(string $directive): void
    {
        if (! isset($this->cspDirectives[$directive])) {
            $this->cspDirectives[$directive] = "'self'";
        }
    }

    /**
     * Remove a CSP directive.
     */
    public function removeDirective(string $directive): void
    {
        unset($this->cspDirectives[$directive]);
    }

    /**
     * Toggle a permissions policy feature.
     */
    public function togglePermission(string $feature): void
    {
        if (isset($this->permissionsFeatures[$feature])) {
            $current = $this->permissionsFeatures[$feature]['enabled'] ?? false;
            $this->permissionsFeatures[$feature]['enabled'] = ! $current;

            if (! $current) {
                // Enabling - default to 'self'
                $this->permissionsFeatures[$feature]['allowlist'] = 'self';
            } else {
                // Disabling - clear allowlist
                $this->permissionsFeatures[$feature]['allowlist'] = '';
            }
        }
    }

    /**
     * Set the active configuration tab.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Toggle advanced options visibility.
     */
    public function toggleAdvanced(): void
    {
        $this->showAdvanced = ! $this->showAdvanced;
    }

    /**
     * Generate environment file content for current configuration.
     */
    public function generateEnvConfig(): string
    {
        $lines = [
            '# Security Headers Configuration',
            '# Generated by HeaderConfigurationManager',
            '',
            'SECURITY_HEADERS_ENABLED='.($this->headersEnabled ? 'true' : 'false'),
            '',
            '# HSTS',
            'SECURITY_HSTS_ENABLED='.($this->hstsEnabled ? 'true' : 'false'),
            'SECURITY_HSTS_MAX_AGE='.$this->hstsMaxAge,
            'SECURITY_HSTS_INCLUDE_SUBDOMAINS='.($this->hstsIncludeSubdomains ? 'true' : 'false'),
            'SECURITY_HSTS_PRELOAD='.($this->hstsPreload ? 'true' : 'false'),
            '',
            '# CSP',
            'SECURITY_CSP_ENABLED='.($this->cspEnabled ? 'true' : 'false'),
            'SECURITY_CSP_REPORT_ONLY='.($this->cspReportOnly ? 'true' : 'false'),
            'SECURITY_CSP_NONCE_ENABLED='.($this->cspNonceEnabled ? 'true' : 'false'),
        ];

        if ($this->cspReportUri) {
            $lines[] = 'SECURITY_CSP_REPORT_URI='.$this->cspReportUri;
        }

        $lines = array_merge($lines, [
            '',
            '# External Services',
            'SECURITY_CSP_JSDELIVR='.($this->jsdelivrEnabled ? 'true' : 'false'),
            'SECURITY_CSP_UNPKG='.($this->unpkgEnabled ? 'true' : 'false'),
            'SECURITY_CSP_GOOGLE_ANALYTICS='.($this->googleAnalyticsEnabled ? 'true' : 'false'),
            'SECURITY_CSP_FACEBOOK='.($this->facebookEnabled ? 'true' : 'false'),
            '',
            '# Other Headers',
            'SECURITY_X_FRAME_OPTIONS='.$this->xFrameOptions,
            'SECURITY_REFERRER_POLICY='.$this->referrerPolicy,
        ]);

        return implode("\n", $lines);
    }

    /**
     * Get CSP directives as array for config.
     *
     * @return array<string, array<string>>
     */
    protected function getDirectivesAsArray(): array
    {
        $directives = [];
        foreach ($this->cspDirectives as $directive => $value) {
            $sources = array_filter(array_map('trim', explode(' ', $value)));
            if (! empty($sources)) {
                $directives[$directive] = $sources;
            }
        }

        return $directives;
    }

    /**
     * Get permissions policy as array for config.
     *
     * @return array<string, array<string>>
     */
    protected function getPermissionsAsArray(): array
    {
        $features = [];
        foreach ($this->permissionsFeatures as $feature => $config) {
            if ($config['enabled'] ?? false) {
                $allowlist = array_filter(array_map('trim', explode(' ', $config['allowlist'] ?? '')));
                $features[$feature] = $allowlist;
            } else {
                $features[$feature] = [];
            }
        }

        return $features;
    }

    /**
     * Get available CSP directives that can be added.
     *
     * @return array<string>
     */
    public function getAvailableDirectives(): array
    {
        return array_diff($this->availableDirectives, array_keys($this->cspDirectives));
    }

    /**
     * Get all permission feature names.
     *
     * @return array<string>
     */
    public function getPermissionFeatures(): array
    {
        return array_keys($this->permissionsFeatures);
    }

    /**
     * Preview the CSP header that would be generated.
     */
    public function previewCspHeader(): string
    {
        $directives = $this->getDirectivesAsArray();
        $parts = [];

        foreach ($directives as $directive => $sources) {
            $parts[] = $directive.' '.implode(' ', $sources);
        }

        return implode('; ', $parts);
    }

    /**
     * Reset to default configuration.
     */
    public function resetToDefaults(): void
    {
        // Clear runtime config and reload defaults
        Config::set('headers', null);
        $this->loadConfiguration();

        $this->saveMessage = 'Configuration reset to defaults.';
        $this->dispatch('configuration-reset');
    }

    /**
     * Save configuration (dispatches event for parent to handle).
     */
    public function saveConfiguration(): void
    {
        $config = [
            'enabled' => $this->headersEnabled,
            'hsts' => [
                'enabled' => $this->hstsEnabled,
                'max_age' => $this->hstsMaxAge,
                'include_subdomains' => $this->hstsIncludeSubdomains,
                'preload' => $this->hstsPreload,
            ],
            'csp' => [
                'enabled' => $this->cspEnabled,
                'report_only' => $this->cspReportOnly,
                'report_uri' => $this->cspReportUri,
                'nonce_enabled' => $this->cspNonceEnabled,
                'directives' => $this->getDirectivesAsArray(),
                'external' => [
                    'jsdelivr' => ['enabled' => $this->jsdelivrEnabled],
                    'unpkg' => ['enabled' => $this->unpkgEnabled],
                    'google_analytics' => ['enabled' => $this->googleAnalyticsEnabled],
                    'facebook' => ['enabled' => $this->facebookEnabled],
                ],
            ],
            'permissions' => [
                'features' => $this->getPermissionsAsArray(),
            ],
            'x_frame_options' => $this->xFrameOptions,
            'referrer_policy' => $this->referrerPolicy,
        ];

        // Dispatch event for parent component or controller to handle persistence
        $this->dispatch('header-configuration-saved', config: $config);

        $this->saveMessage = 'Configuration saved successfully.';
    }

    /**
     * Clear notification messages.
     */
    public function clearMessages(): void
    {
        $this->saveMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Render the component.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('core::headers.livewire.header-configuration-manager');
    }
}
