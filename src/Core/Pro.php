<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Composer\InstalledVersions;

/**
 * Pro Feature Detection for Core Components.
 *
 * Core itself has no "Pro" tier - it's free and open source.
 *
 * However, Core wraps components from packages that DO have Pro versions:
 *   - Flux UI (livewire/flux-pro) - calendar, editor, chart, kanban, etc.
 *   - FontAwesome Pro - light, thin, duotone, sharp, jelly icon styles
 *
 * This class detects whether you have these Pro packages installed and:
 *   - Enables Pro features automatically when detected
 *   - Falls back gracefully to Free equivalents when not
 *   - Throws helpful exceptions in dev if you use Pro components without a licence
 *
 * @example
 *   // In a Pro component wrapper:
 *
 *   @php(App\Core\Pro::requireFluxPro('core:calendar'))
 *   <flux:calendar {{ $attributes }} />
 *
 * @see https://fluxui.dev/pricing - Flux Pro licence
 * @see https://fontawesome.com/plans - FontAwesome Pro licence
 */
class Pro
{
    protected static ?bool $fluxPro = null;

    protected static ?bool $fontAwesomePro = null;

    /**
     * Check if Flux Pro is installed.
     */
    public static function hasFluxPro(): bool
    {
        if (self::$fluxPro === null) {
            self::$fluxPro = InstalledVersions::isInstalled('livewire/flux-pro');
        }

        return self::$fluxPro;
    }

    /**
     * Check if FontAwesome Pro is configured.
     */
    public static function hasFontAwesomePro(): bool
    {
        if (self::$fontAwesomePro === null) {
            self::$fontAwesomePro = (bool) config('core.fontawesome.pro', false);
        }

        return self::$fontAwesomePro;
    }

    /**
     * Components that require Flux Pro.
     */
    public static function fluxProComponents(): array
    {
        return [
            'calendar',
            'date-picker',
            'time-picker',
            'editor',
            'composer',
            'chart',
            'kanban',
            'command',
            'context',
            'autocomplete',
            'pillbox',
            'slider',
            'file-upload',
        ];
    }

    /**
     * Check if a component requires Flux Pro.
     */
    public static function requiresFluxPro(string $component): bool
    {
        // Normalize: remove core:/flux: prefix, get base component
        $component = preg_replace('/^(core|flux):/', '', $component);
        $component = explode('.', $component)[0];

        return in_array($component, self::fluxProComponents(), true);
    }

    /**
     * Assert Flux Pro is available.
     *
     * Call at the top of Pro component wrappers. In dev, throws a helpful
     * exception. In production, fails silently (component won't render).
     *
     * @throws \RuntimeException In dev when Flux Pro not installed
     */
    public static function requireFluxPro(string $component = ''): void
    {
        if (self::hasFluxPro()) {
            return;
        }

        if (app()->environment('local', 'development', 'testing')) {
            $message = $component
                ? "Flux Pro component <{$component}> requires a licence."
                : 'Flux Pro component requires a licence.';

            throw new \RuntimeException(
                "{$message} Purchase at: https://fluxui.dev/pricing"
            );
        }
    }

    /**
     * Get available FontAwesome styles based on Pro/Free.
     */
    public static function fontAwesomeStyles(): array
    {
        if (self::hasFontAwesomePro()) {
            return ['solid', 'regular', 'light', 'thin', 'duotone', 'brands', 'sharp', 'jelly'];
        }

        return ['solid', 'regular', 'brands'];
    }

    /**
     * Get fallback style when Pro style requested but Pro not available.
     */
    public static function fontAwesomeFallback(string $style): string
    {
        if (self::hasFontAwesomePro()) {
            return $style;
        }

        return match ($style) {
            'light', 'thin' => 'regular',
            'duotone', 'sharp', 'jelly' => 'solid',
            default => $style,
        };
    }

    /**
     * Class prefix for a FontAwesome style.
     *
     * In FontAwesome 7 a family is TWO classes — the family and the weight —
     * so `fa-jelly fa-rocket` loses its family binding and silently renders as
     * classic. Only the classic styles are a single class. Callers must use
     * this rather than interpolating "fa-{$style}".
     *
     *   Pro::fontAwesomeClasses('solid');   // "fa-solid"
     *   Pro::fontAwesomeClasses('jelly');   // "fa-jelly fa-regular"
     *   Pro::fontAwesomeClasses('sharp');   // "fa-sharp fa-solid"
     */
    public static function fontAwesomeClasses(string $style): string
    {
        return match ($style) {
            'solid', 'regular', 'light', 'thin', 'brands' => 'fa-'.$style,
            'duotone' => 'fa-duotone',
            'sharp' => 'fa-sharp fa-solid',
            'sharp-solid' => 'fa-sharp fa-solid',
            'sharp-regular' => 'fa-sharp fa-regular',
            'sharp-light' => 'fa-sharp fa-light',
            'sharp-thin' => 'fa-sharp fa-thin',
            'sharp-duotone' => 'fa-sharp-duotone fa-solid',
            'jelly' => 'fa-jelly fa-regular',
            'jelly-fill' => 'fa-jelly-fill fa-regular',
            'jelly-duo' => 'fa-jelly-duo fa-regular',
            'slab' => 'fa-slab fa-regular',
            'slab-press' => 'fa-slab-press fa-regular',
            'utility' => 'fa-utility fa-semibold',
            'notdog' => 'fa-notdog fa-solid',
            'chisel' => 'fa-chisel fa-regular',
            'etch' => 'fa-etch fa-solid',
            'mosaic' => 'fa-mosaic fa-solid',
            'pixel' => 'fa-pixel fa-regular',
            'thumbprint' => 'fa-thumbprint fa-light',
            'vellum' => 'fa-vellum fa-solid',
            'whiteboard' => 'fa-whiteboard fa-semibold',
            default => 'fa-solid',
        };
    }

    /**
     * Resolve an icon to the name that will actually render.
     *
     * A Pro class on a free kit renders NOTHING — no error, no placeholder,
     * just a gap where an icon should be, which is invisible in review and
     * only shows up in production. So a caller names the free icon that is
     * good enough, and it is used whenever Pro is absent.
     *
     *   Pro::fontAwesomeName('face-viewfinder', 'camera');
     *   // Pro kit  -> "face-viewfinder"
     *   // Free kit -> "camera"
     */
    public static function fontAwesomeName(string $name, ?string $fallback = null): string
    {
        if ($fallback === null || self::hasFontAwesomePro()) {
            return $name;
        }

        return $fallback;
    }

    /**
     * Clear cached detection (for testing).
     */
    public static function clearCache(): void
    {
        self::$fluxPro = null;
        self::$fontAwesomePro = null;
    }
}
