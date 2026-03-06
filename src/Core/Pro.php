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
     * Clear cached detection (for testing).
     */
    public static function clearCache(): void
    {
        self::$fluxPro = null;
        self::$fontAwesomePro = null;
    }
}
