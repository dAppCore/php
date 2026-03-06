<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Service OG Image Generator.
 *
 * Generates branded Open Graph images for service landing pages.
 * Each service has a distinct colour accent matching the Service.Host branding.
 */
class ServiceOgImageService
{
    protected int $width = 1200;

    protected int $height = 630;

    /**
     * Service configuration: name, tagline, and brand colour.
     */
    protected array $services = [
        'biohost' => [
            'name' => 'Bio',
            'tagline' => 'Link-in-bio & website builder',
            'description' => 'Create stunning bio pages with 68 content blocks, QR codes, and short links.',
            'color' => '#8b5cf6', // violet-500
            'gradient' => ['#8b5cf6', '#6366f1'], // violet to indigo
        ],
        'socialhost' => [
            'name' => 'Social',
            'tagline' => 'Social Media Management',
            'description' => 'Schedule posts to 22+ social networks from one dashboard.',
            'color' => '#3b82f6', // blue-500
            'gradient' => ['#3b82f6', '#0ea5e9'], // blue to sky
        ],
        'analyticshost' => [
            'name' => 'Analytics',
            'tagline' => 'Privacy-First Mod Analytics',
            'description' => 'No cookies, under 2KB, GDPR compliant. Heatmaps and session recordings.',
            'color' => '#06b6d4', // cyan-500
            'gradient' => ['#06b6d4', '#14b8a6'], // cyan to teal
        ],
        'trusthost' => [
            'name' => 'Trust',
            'tagline' => 'Social Proof Widgets',
            'description' => 'Live sales notifications, review widgets, and visitor counters.',
            'color' => '#f97316', // orange-500
            'gradient' => ['#f97316', '#ef4444'], // orange to red
        ],
        'notifyhost' => [
            'name' => 'Notify',
            'tagline' => 'Browser Push Notifications',
            'description' => 'Send push notifications to any browser. No app required.',
            'color' => '#eab308', // yellow-500
            'gradient' => ['#eab308', '#f59e0b'], // yellow to amber
        ],
        'mailhost' => [
            'name' => 'Mail',
            'tagline' => 'Professional Email',
            'description' => 'Unlimited aliases, custom domains, and full IMAP/SMTP access.',
            'color' => '#6366f1', // indigo-500
            'gradient' => ['#6366f1', '#8b5cf6'], // indigo to violet
        ],
    ];

    /**
     * Generate an OG image for a service.
     *
     * @param  string  $service  Service key (biohost, socialhost, etc.)
     * @return string|null Storage path to the generated image, or null if invalid service
     */
    public function generate(string $service): ?string
    {
        $service = strtolower($service);

        if (! isset($this->services[$service])) {
            return null;
        }

        $config = $this->services[$service];

        // Create gradient background
        $image = $this->createGradientBackground($config['gradient']);

        // Add overlay for better text contrast
        $image->rectangle(0, 0, $this->width, $this->height, function ($draw) {
            $draw->background('rgba(0, 0, 0, 0.35)');
        });

        // Add subtle pattern/texture
        $this->addSubtlePattern($image);

        // Render content
        $this->renderServiceContent($image, $config);

        // Encode and save
        $filename = $this->getFilename($service);
        $encoded = $image->encode('png', 95);

        Storage::disk('public')->put($filename, $encoded);

        return Storage::disk('public')->url($filename);
    }

    /**
     * Get the URL for a service OG image.
     */
    public function getUrl(string $service): ?string
    {
        $service = strtolower($service);

        if (! isset($this->services[$service])) {
            return null;
        }

        return url("/og/services/{$service}.png");
    }

    /**
     * Check if an OG image exists for a service.
     */
    public function exists(string $service): bool
    {
        $service = strtolower($service);
        $filename = $this->getFilename($service);

        return Storage::disk('public')->exists($filename);
    }

    /**
     * Get the filename for a service's OG image.
     */
    protected function getFilename(string $service): string
    {
        return "og-images/services/{$service}.png";
    }

    /**
     * Create a gradient background image.
     */
    protected function createGradientBackground(array $gradient): \Intervention\Image\Image
    {
        // Create base canvas with start colour
        $image = Image::canvas($this->width, $this->height, $gradient[0]);

        // Create gradient effect using overlapping rectangles
        $steps = 50;
        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / $steps;
            $color = $this->interpolateColor($gradient[0], $gradient[1], $ratio);

            // Diagonal gradient from top-left to bottom-right
            $x = (int) ($this->width * $ratio);
            $image->rectangle($x, 0, $this->width, $this->height, function ($draw) use ($color) {
                $draw->background($color);
            });
        }

        return $image;
    }

    /**
     * Interpolate between two hex colours.
     */
    protected function interpolateColor(string $color1, string $color2, float $ratio): string
    {
        $r1 = hexdec(substr($color1, 1, 2));
        $g1 = hexdec(substr($color1, 3, 2));
        $b1 = hexdec(substr($color1, 5, 2));

        $r2 = hexdec(substr($color2, 1, 2));
        $g2 = hexdec(substr($color2, 3, 2));
        $b2 = hexdec(substr($color2, 5, 2));

        $r = (int) ($r1 + ($r2 - $r1) * $ratio);
        $g = (int) ($g1 + ($g2 - $g1) * $ratio);
        $b = (int) ($b1 + ($b2 - $b1) * $ratio);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Add a subtle dot pattern for visual interest.
     */
    protected function addSubtlePattern(\Intervention\Image\Image $image): void
    {
        // Add subtle radial gradient glow at top-right
        for ($i = 0; $i < 5; $i++) {
            $size = 400 - ($i * 60);
            $opacity = 0.03 + ($i * 0.01);
            $x = $this->width - 100;
            $y = 100;

            $image->circle($size, $x, $y, function ($draw) use ($opacity) {
                $draw->background("rgba(255, 255, 255, {$opacity})");
            });
        }

        // Add subtle glow at bottom-left
        for ($i = 0; $i < 3; $i++) {
            $size = 300 - ($i * 50);
            $opacity = 0.02 + ($i * 0.01);
            $x = 100;
            $y = $this->height - 80;

            $image->circle($size, $x, $y, function ($draw) use ($opacity) {
                $draw->background("rgba(255, 255, 255, {$opacity})");
            });
        }
    }

    /**
     * Render the service content on the image.
     */
    protected function renderServiceContent(\Intervention\Image\Image $image, array $config): void
    {
        $fontBold = $this->getFontPath('Inter-Bold.ttf');
        $fontSemiBold = $this->getFontPath('Inter-SemiBold.ttf');
        $fontMedium = $this->getFontPath('Inter-Medium.ttf');
        $fontRegular = $this->getFontPath('Inter-Regular.ttf');

        // Service name with dot styling: "Bio.Host"
        // The dot gets the accent colour
        $serviceName = $config['name'];
        $accentColor = $config['color'];

        // Draw service name (large, bold)
        // Position: upper portion of the image
        $nameY = 220;

        // Draw "Service" part
        $image->text($serviceName, 100, $nameY, function ($font) use ($fontBold) {
            if ($fontBold) {
                $font->file($fontBold);
            }
            $font->size(72);
            $font->color('#ffffff');
            $font->align('left');
            $font->valign('middle');
        });

        // Estimate width of service name for dot placement
        $charWidth = 43; // Approximate width per character at size 72
        $dotX = 100 + (strlen($serviceName) * $charWidth);

        // Draw the coloured dot
        $image->text('.', $dotX, $nameY, function ($font) use ($fontBold, $accentColor) {
            if ($fontBold) {
                $font->file($fontBold);
            }
            $font->size(72);
            $font->color($accentColor);
            $font->align('left');
            $font->valign('middle');
        });

        // Draw "Host"
        $hostX = $dotX + 20;
        $image->text('Host', $hostX, $nameY, function ($font) use ($fontBold) {
            if ($fontBold) {
                $font->file($fontBold);
            }
            $font->size(72);
            $font->color('#ffffff');
            $font->align('left');
            $font->valign('middle');
        });

        // Draw tagline
        $image->text($config['tagline'], 100, $nameY + 80, function ($font) use ($fontSemiBold) {
            if ($fontSemiBold) {
                $font->file($fontSemiBold);
            }
            $font->size(32);
            $font->color('#e5e7eb');
            $font->align('left');
            $font->valign('middle');
        });

        // Draw description
        $image->text($config['description'], 100, $nameY + 140, function ($font) use ($fontRegular) {
            if ($fontRegular) {
                $font->file($fontRegular);
            }
            $font->size(24);
            $font->color('#9ca3af');
            $font->align('left');
            $font->valign('middle');
        });

        // Draw Host UK branding at bottom
        $image->text('host.uk.com', 100, $this->height - 60, function ($font) use ($fontMedium) {
            if ($fontMedium) {
                $font->file($fontMedium);
            }
            $font->size(20);
            $font->color('#6b7280');
            $font->align('left');
            $font->valign('middle');
        });

        // Draw "Beta" badge if applicable
        $this->drawBetaBadge($image, $this->width - 100, $this->height - 60);
    }

    /**
     * Draw a Beta badge.
     */
    protected function drawBetaBadge(\Intervention\Image\Image $image, int $x, int $y): void
    {
        $fontMedium = $this->getFontPath('Inter-Medium.ttf');

        // Draw badge background
        $badgeWidth = 60;
        $badgeHeight = 24;
        $image->rectangle(
            $x - $badgeWidth,
            $y - $badgeHeight / 2,
            $x,
            $y + $badgeHeight / 2,
            function ($draw) {
                $draw->background('rgba(139, 92, 246, 0.3)'); // violet with transparency
                $draw->border(1, '#8b5cf6');
            }
        );

        // Draw badge text
        $image->text('BETA', $x - $badgeWidth / 2, $y, function ($font) use ($fontMedium) {
            if ($fontMedium) {
                $font->file($fontMedium);
            }
            $font->size(12);
            $font->color('#a78bfa');
            $font->align('center');
            $font->valign('middle');
        });
    }

    /**
     * Get font file path if it exists.
     */
    protected function getFontPath(string $filename): ?string
    {
        // Check in public/fonts first
        $path = public_path("fonts/{$filename}");
        if (file_exists($path)) {
            return $path;
        }

        // Check in resources/fonts
        $path = resource_path("fonts/{$filename}");
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Get all available services.
     *
     * @return array<string, array>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Check if a service key is valid.
     */
    public function isValidService(string $service): bool
    {
        return isset($this->services[strtolower($service)]);
    }

    /**
     * Regenerate all service OG images.
     *
     * @return array<string, string> Map of service => generated URL
     */
    public function regenerateAll(): array
    {
        $results = [];

        foreach (array_keys($this->services) as $service) {
            $results[$service] = $this->generate($service);
        }

        return $results;
    }
}
