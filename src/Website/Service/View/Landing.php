<?php

declare(strict_types=1);

namespace Core\Website\Service\View;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Generic Service Landing Page.
 *
 * Public landing page for services.
 * Uses workspace data for dynamic theming.
 */
class Landing extends Component
{
    public array $workspace = [];

    public function mount(): void
    {
        // Extract subdomain from host (e.g., social.host.test → social)
        $host = request()->getHost();
        $slug = $this->extractSubdomain($host);

        // Try to resolve workspace from app container if service exists
        if ($slug && app()->bound('workspace.service')) {
            $this->workspace = app('workspace.service')->get($slug) ?? [];
        }

        // Fallback to app config defaults
        if (empty($this->workspace)) {
            $this->workspace = [
                'name' => config('core.app.name', config('app.name', 'Service')),
                'slug' => 'service',
                'icon' => config('core.app.icon', 'cube'),
                'color' => config('core.app.color', 'violet'),
                'description' => config('core.app.description', 'A powerful platform'),
            ];
        }
    }

    /**
     * Extract subdomain from hostname.
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Handle patterns like social.host.test, social.host.uk.com
        if (preg_match('/^([a-z]+)\.host\.(test|localhost|uk\.com)$/i', $host, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get features for the service.
     */
    public function getFeatures(): array
    {
        // Generic features - services can override in their own Landing
        return [
            [
                'icon' => 'rocket',
                'title' => 'Easy to use',
                'description' => 'Get started in minutes with our intuitive interface.',
            ],
            [
                'icon' => 'shield-check',
                'title' => 'Secure by default',
                'description' => 'Built with security and privacy at the core.',
            ],
            [
                'icon' => 'chart-line',
                'title' => 'Analytics included',
                'description' => 'Track performance with built-in analytics.',
            ],
            [
                'icon' => 'puzzle-piece',
                'title' => 'Modular architecture',
                'description' => 'Extend with modules to fit your exact needs.',
            ],
        ];
    }

    public function render(): View
    {
        $appName = config('core.app.name', config('app.name', 'Service'));

        return view('service::landing', [
            'workspace' => $this->workspace,
            'features' => $this->getFeatures(),
        ])->layout('service::layouts.service', [
            'title' => $this->workspace['name'] !== $appName
                ? $this->workspace['name'].' - '.$appName
                : $appName,
            'workspace' => $this->workspace,
        ]);
    }
}
