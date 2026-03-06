<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Website\Service\View;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Generic Service Features Page.
 *
 * Displays service features with dynamic theming.
 */
class Features extends Component
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
        if (preg_match('/^([a-z]+)\.host\.(test|localhost|uk\.com)$/i', $host, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get detailed features for this service.
     */
    public function getFeatures(): array
    {
        $slug = $this->workspace['slug'] ?? 'service';

        // Service-specific features
        return match ($slug) {
            'social' => $this->getSocialFeatures(),
            'analytics' => $this->getAnalyticsFeatures(),
            'notify' => $this->getNotifyFeatures(),
            'trust' => $this->getTrustFeatures(),
            'support' => $this->getSupportFeatures(),
            default => $this->getDefaultFeatures(),
        };
    }

    protected function getSocialFeatures(): array
    {
        return [
            ['icon' => 'calendar', 'title' => 'Schedule posts', 'description' => 'Plan your content calendar weeks in advance with our visual scheduler.'],
            ['icon' => 'share-nodes', 'title' => 'Multi-platform publishing', 'description' => 'Publish to 20+ social networks from a single dashboard.'],
            ['icon' => 'chart-pie', 'title' => 'Analytics & insights', 'description' => 'Track engagement, reach, and growth across all your accounts.'],
            ['icon' => 'users', 'title' => 'Team collaboration', 'description' => 'Work together with approval workflows and role-based access.'],
            ['icon' => 'wand-magic-sparkles', 'title' => 'AI content assistant', 'description' => 'Generate captions, hashtags, and post ideas with AI.'],
            ['icon' => 'inbox', 'title' => 'Unified inbox', 'description' => 'Manage comments and messages from all platforms in one place.'],
        ];
    }

    protected function getAnalyticsFeatures(): array
    {
        return [
            ['icon' => 'cookie-bite', 'title' => 'No cookies required', 'description' => 'Privacy-focused tracking without consent banners.'],
            ['icon' => 'bolt', 'title' => 'Lightweight script', 'description' => 'Under 1KB script that won\'t slow down your site.'],
            ['icon' => 'chart-line', 'title' => 'Real-time dashboard', 'description' => 'See visitors on your site as they browse.'],
            ['icon' => 'route', 'title' => 'Goal tracking', 'description' => 'Set up funnels and conversion goals to measure success.'],
            ['icon' => 'flask', 'title' => 'A/B testing', 'description' => 'Test variations and measure statistical significance.'],
            ['icon' => 'file-export', 'title' => 'Data export', 'description' => 'Export your data anytime in CSV or JSON format.'],
        ];
    }

    protected function getNotifyFeatures(): array
    {
        return [
            ['icon' => 'bell', 'title' => 'Web push notifications', 'description' => 'Reach subscribers directly in their browser.'],
            ['icon' => 'users-gear', 'title' => 'Audience segments', 'description' => 'Target specific groups based on behaviour and preferences.'],
            ['icon' => 'diagram-project', 'title' => 'Automation flows', 'description' => 'Create drip campaigns triggered by user actions.'],
            ['icon' => 'vials', 'title' => 'A/B testing', 'description' => 'Test different messages to optimise engagement.'],
            ['icon' => 'chart-simple', 'title' => 'Delivery analytics', 'description' => 'Track delivery, clicks, and conversion rates.'],
            ['icon' => 'clock', 'title' => 'Scheduled sends', 'description' => 'Schedule notifications for optimal delivery times.'],
        ];
    }

    protected function getTrustFeatures(): array
    {
        return [
            ['icon' => 'comment-dots', 'title' => 'Social proof popups', 'description' => 'Show real-time purchase and signup notifications.'],
            ['icon' => 'star', 'title' => 'Review collection', 'description' => 'Collect and display customer reviews automatically.'],
            ['icon' => 'bullseye', 'title' => 'Smart targeting', 'description' => 'Show notifications to the right visitors at the right time.'],
            ['icon' => 'palette', 'title' => 'Custom styling', 'description' => 'Match notifications to your brand with full CSS control.'],
            ['icon' => 'chart-column', 'title' => 'Conversion tracking', 'description' => 'Measure the impact on your conversion rates.'],
            ['icon' => 'plug', 'title' => 'Easy integration', 'description' => 'Add to any website with a single script tag.'],
        ];
    }

    protected function getSupportFeatures(): array
    {
        return [
            ['icon' => 'inbox', 'title' => 'Shared inbox', 'description' => 'Manage customer emails from a unified team inbox.'],
            ['icon' => 'book', 'title' => 'Help centre', 'description' => 'Build a self-service knowledge base for customers.'],
            ['icon' => 'comments', 'title' => 'Live chat widget', 'description' => 'Embed a chat widget on your website for instant support.'],
            ['icon' => 'clock-rotate-left', 'title' => 'SLA tracking', 'description' => 'Set response time targets and track performance.'],
            ['icon' => 'reply', 'title' => 'Canned responses', 'description' => 'Save time with pre-written replies for common questions.'],
            ['icon' => 'tags', 'title' => 'Ticket management', 'description' => 'Organise conversations with tags and custom fields.'],
        ];
    }

    protected function getDefaultFeatures(): array
    {
        return [
            ['icon' => 'rocket', 'title' => 'Easy to use', 'description' => 'Get started in minutes with our intuitive interface.'],
            ['icon' => 'shield-check', 'title' => 'Secure by default', 'description' => 'Built with security and privacy at the core.'],
            ['icon' => 'chart-line', 'title' => 'Analytics included', 'description' => 'Track performance with built-in analytics.'],
            ['icon' => 'puzzle-piece', 'title' => 'Modular architecture', 'description' => 'Extend with modules to fit your exact needs.'],
            ['icon' => 'headset', 'title' => 'UK-based support', 'description' => 'Get help from our friendly support team.'],
            ['icon' => 'code', 'title' => 'Developer friendly', 'description' => 'Full API access and webhook integrations.'],
        ];
    }

    public function render(): View
    {
        $appName = config('core.app.name', config('app.name', 'Service'));

        return view('service::features', [
            'workspace' => $this->workspace,
            'features' => $this->getFeatures(),
        ])->layout('service::layouts.service', [
            'title' => 'Features - '.($this->workspace['name'] ?? $appName),
            'workspace' => $this->workspace,
        ]);
    }
}
