<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\Validation;

use Illuminate\Support\Facades\Log;

/**
 * Validates menu item icons against known FontAwesome icon sets.
 *
 * This validator ensures that icons used in admin menu items are valid
 * FontAwesome icons. It supports both shorthand (e.g., 'home') and full
 * format (e.g., 'fas fa-home', 'fa-solid fa-home').
 *
 * ## Usage
 *
 * ```php
 * $validator = new IconValidator();
 *
 * // Validate a single icon
 * if ($validator->isValid('home')) {
 *     // Icon is valid
 * }
 *
 * // Get validation errors
 * $errors = $validator->validate(['home', 'invalid-icon']);
 * ```
 *
 * ## Icon Formats
 *
 * The validator accepts multiple formats:
 * - Shorthand: `home`, `user`, `cog`
 * - Full class: `fas fa-home`, `fa-solid fa-home`
 * - Brand icons: `fab fa-github`, `fa-brands fa-twitter`
 *
 * ## Extending
 *
 * Add custom icons via the `addCustomIcon()` method or register
 * icon packs with `registerIconPack()`.
 */
class IconValidator
{
    /**
     * Core FontAwesome solid icons (common subset).
     *
     * This is not exhaustive - FontAwesome has 2000+ icons.
     * Configure additional icons via config or custom icon packs.
     *
     * @var array<string>
     */
    protected const SOLID_ICONS = [
        // Navigation & UI
        'home', 'house', 'bars', 'times', 'close', 'xmark', 'check', 'plus', 'minus',
        'arrow-left', 'arrow-right', 'arrow-up', 'arrow-down', 'chevron-left',
        'chevron-right', 'chevron-up', 'chevron-down', 'angle-left', 'angle-right',
        'angle-up', 'angle-down', 'caret-left', 'caret-right', 'caret-up', 'caret-down',
        'ellipsis', 'ellipsis-vertical', 'grip', 'grip-vertical',

        // Common Objects
        'user', 'users', 'user-plus', 'user-minus', 'user-gear', 'user-shield',
        'user-check', 'user-xmark', 'user-group', 'people-group',
        'gear', 'gears', 'cog', 'cogs', 'sliders', 'wrench', 'screwdriver',
        'file', 'file-lines', 'file-pdf', 'file-image', 'file-code', 'file-export',
        'file-import', 'folder', 'folder-open', 'folder-plus', 'folder-minus',
        'envelope', 'envelope-open', 'paper-plane', 'inbox', 'mailbox',
        'phone', 'mobile', 'tablet', 'laptop', 'desktop', 'computer',
        'calendar', 'calendar-days', 'calendar-check', 'calendar-plus',
        'clock', 'stopwatch', 'hourglass', 'timer',
        'bell', 'bell-slash', 'bell-concierge',
        'bookmark', 'bookmarks', 'flag', 'tag', 'tags',
        'star', 'star-half', 'heart', 'thumbs-up', 'thumbs-down',
        'comment', 'comments', 'message', 'quote-left', 'quote-right',
        'image', 'images', 'camera', 'video', 'film', 'photo-film',
        'music', 'headphones', 'microphone', 'volume-high', 'volume-low', 'volume-off',
        'play', 'pause', 'stop', 'forward', 'backward', 'circle-play',
        'link', 'link-slash', 'chain', 'chain-broken', 'unlink',
        'key', 'lock', 'lock-open', 'unlock', 'shield', 'shield-halved',
        'eye', 'eye-slash', 'glasses',
        'magnifying-glass', 'search', 'filter', 'sort', 'sort-up', 'sort-down',

        // E-commerce & Business
        'cart-shopping', 'basket-shopping', 'bag-shopping', 'store', 'shop',
        'credit-card', 'money-bill', 'money-bill-wave', 'coins', 'wallet',
        'receipt', 'barcode', 'qrcode', 'box', 'boxes', 'package',
        'truck', 'shipping-fast', 'dolly', 'warehouse',
        'chart-line', 'chart-bar', 'chart-pie', 'chart-area', 'chart-simple',
        'arrow-trend-up', 'arrow-trend-down',
        'briefcase', 'suitcase', 'building', 'buildings', 'city', 'industry',
        'handshake', 'handshake-angle', 'hands-holding',

        // Communication & Social
        'at', 'hashtag', 'share', 'share-nodes', 'share-from-square',
        'globe', 'earth-americas', 'earth-europe', 'earth-asia',
        'rss', 'wifi', 'signal', 'broadcast-tower',
        'bullhorn', 'megaphone', 'newspaper',

        // Content & Media
        'pen', 'pencil', 'pen-to-square', 'edit', 'eraser', 'highlighter',
        'palette', 'paintbrush', 'brush', 'spray-can',
        'align-left', 'align-center', 'align-right', 'align-justify',
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript',
        'list', 'list-ul', 'list-ol', 'list-check', 'table', 'table-cells',
        'code', 'terminal', 'code-branch', 'code-merge', 'code-pull-request',
        'cube', 'cubes', 'puzzle-piece', 'shapes',

        // Actions & States
        'plus', 'minus', 'times', 'check', 'xmark',
        'circle', 'circle-check', 'circle-xmark', 'circle-info', 'circle-question',
        'circle-exclamation', 'circle-notch', 'circle-dot', 'circle-half-stroke',
        'square', 'square-check', 'square-xmark', 'square-plus', 'square-minus',
        'triangle-exclamation', 'exclamation', 'question', 'info',
        'rotate', 'rotate-right', 'rotate-left', 'sync', 'refresh', 'redo', 'undo',
        'arrows-rotate', 'arrows-spin', 'spinner', 'circle-notch',
        'download', 'upload', 'cloud-download', 'cloud-upload',
        'save', 'floppy-disk', 'copy', 'paste', 'clipboard', 'trash', 'trash-can',
        'print', 'share', 'export', 'external-link', 'expand', 'compress',

        // Security & Privacy
        'shield', 'shield-halved', 'shield-check', 'user-shield',
        'fingerprint', 'id-card', 'id-badge', 'passport',
        'mask', 'ban', 'block', 'circle-stop',

        // Development & Tech
        'server', 'database', 'hdd', 'memory', 'microchip',
        'plug', 'power-off', 'bolt', 'battery-full', 'battery-half', 'battery-empty',
        'robot', 'brain', 'lightbulb', 'wand-magic', 'wand-magic-sparkles',
        'bug', 'bug-slash', 'vial', 'flask', 'microscope',

        // Locations & Maps
        'location-dot', 'location-pin', 'map', 'map-pin', 'map-marker',
        'compass', 'directions', 'route', 'road',

        // Nature & Weather
        'sun', 'moon', 'cloud', 'cloud-sun', 'cloud-moon', 'cloud-rain',
        'snowflake', 'wind', 'temperature-high', 'temperature-low',
        'tree', 'leaf', 'seedling', 'flower', 'mountain',

        // Misc
        'layer-group', 'layers', 'sitemap', 'network-wired',
        'grip-lines', 'grip-lines-vertical', 'border-all',
        'award', 'trophy', 'medal', 'crown', 'gem',
        'gift', 'cake-candles', 'champagne-glasses',
        'graduation-cap', 'book', 'book-open', 'bookmark',
        'hospital', 'stethoscope', 'heart-pulse', 'pills', 'syringe',
    ];

    /**
     * Brand icons (FontAwesome brands).
     *
     * @var array<string>
     */
    protected const BRAND_ICONS = [
        // Social Media
        'facebook', 'facebook-f', 'twitter', 'x-twitter', 'instagram', 'linkedin',
        'linkedin-in', 'youtube', 'tiktok', 'snapchat', 'pinterest', 'reddit',
        'tumblr', 'whatsapp', 'telegram', 'discord', 'slack', 'twitch',

        // Development
        'github', 'gitlab', 'bitbucket', 'git', 'git-alt', 'docker',
        'npm', 'node', 'node-js', 'php', 'python', 'java', 'js', 'js-square',
        'html5', 'css3', 'css3-alt', 'sass', 'less', 'bootstrap',
        'react', 'vuejs', 'angular', 'laravel', 'symfony',
        'aws', 'digital-ocean', 'google-cloud', 'microsoft', 'azure',

        // Companies
        'apple', 'google', 'amazon', 'microsoft', 'meta', 'stripe',
        'paypal', 'cc-visa', 'cc-mastercard', 'cc-amex', 'cc-stripe',
        'shopify', 'wordpress', 'drupal', 'joomla', 'magento',

        // Services
        'dropbox', 'google-drive', 'trello', 'jira', 'confluence',
        'figma', 'sketch', 'invision', 'adobe', 'behance', 'dribbble',
        'vimeo', 'spotify', 'soundcloud', 'deezer', 'lastfm',
        'mailchimp', 'hubspot', 'salesforce', 'zendesk',

        // Misc
        'android', 'apple', 'windows', 'linux', 'ubuntu', 'fedora', 'chrome',
        'firefox', 'safari', 'edge', 'opera', 'internet-explorer',
        'bluetooth', 'usb', 'wifi',
    ];

    /**
     * Custom registered icons.
     *
     * @var array<string>
     */
    protected array $customIcons = [];

    /**
     * Icon packs (name => icons array).
     *
     * @var array<string, array<string>>
     */
    protected array $iconPacks = [];

    /**
     * Whether to log validation warnings.
     */
    protected bool $logWarnings = true;

    /**
     * Whether strict validation is enabled.
     */
    protected bool $strictMode = false;

    public function __construct()
    {
        $this->strictMode = (bool) config('core.admin_menu.strict_icon_validation', false);
        $this->logWarnings = (bool) config('core.admin_menu.log_icon_warnings', true);

        // Load custom icons from config
        $customIcons = config('core.admin_menu.custom_icons', []);
        if (is_array($customIcons)) {
            $this->customIcons = $customIcons;
        }
    }

    /**
     * Check if an icon is valid.
     */
    public function isValid(string $icon): bool
    {
        $normalized = $this->normalizeIcon($icon);

        return $this->isKnownIcon($normalized);
    }

    /**
     * Validate an icon and return errors if any.
     *
     * @return array<string> Array of error messages
     */
    public function validate(string $icon): array
    {
        $errors = [];

        if (empty($icon)) {
            $errors[] = 'Icon name cannot be empty';

            return $errors;
        }

        $normalized = $this->normalizeIcon($icon);

        if (! $this->isKnownIcon($normalized)) {
            $errors[] = sprintf(
                "Unknown icon '%s'. Ensure it's a valid FontAwesome icon or add it to custom icons.",
                $icon
            );

            if ($this->logWarnings) {
                Log::warning("Unknown admin menu icon: {$icon}");
            }
        }

        return $errors;
    }

    /**
     * Validate multiple icons at once.
     *
     * @param  array<string>  $icons
     * @return array<string, array<string>> Icon => errors mapping
     */
    public function validateMany(array $icons): array
    {
        $results = [];

        foreach ($icons as $icon) {
            $errors = $this->validate($icon);
            if (! empty($errors)) {
                $results[$icon] = $errors;
            }
        }

        return $results;
    }

    /**
     * Normalize an icon name to its base form.
     *
     * Handles various formats:
     * - 'home' => 'home'
     * - 'fa-home' => 'home'
     * - 'fas fa-home' => 'home'
     * - 'fa-solid fa-home' => 'home'
     * - 'fab fa-github' => 'github' (brand)
     */
    public function normalizeIcon(string $icon): string
    {
        $icon = trim($icon);

        // Remove FontAwesome class prefixes
        $icon = preg_replace('/^(fas?|far|fab|fa-solid|fa-regular|fa-brands)\s+/', '', $icon);

        // Remove 'fa-' prefix
        $icon = preg_replace('/^fa-/', '', $icon ?? $icon);

        return strtolower($icon ?? '');
    }

    /**
     * Check if the normalized icon name is in any known icon set.
     */
    protected function isKnownIcon(string $normalizedIcon): bool
    {
        // Check core solid icons
        if (in_array($normalizedIcon, self::SOLID_ICONS, true)) {
            return true;
        }

        // Check brand icons
        if (in_array($normalizedIcon, self::BRAND_ICONS, true)) {
            return true;
        }

        // Check custom icons
        if (in_array($normalizedIcon, $this->customIcons, true)) {
            return true;
        }

        // Check registered icon packs
        foreach ($this->iconPacks as $icons) {
            if (in_array($normalizedIcon, $icons, true)) {
                return true;
            }
        }

        // If not strict mode, allow any icon (for extensibility)
        if (! $this->strictMode) {
            return true;
        }

        return false;
    }

    /**
     * Add a custom icon to the validator.
     */
    public function addCustomIcon(string $icon): self
    {
        $normalized = $this->normalizeIcon($icon);
        if (! in_array($normalized, $this->customIcons, true)) {
            $this->customIcons[] = $normalized;
        }

        return $this;
    }

    /**
     * Add multiple custom icons.
     *
     * @param  array<string>  $icons
     */
    public function addCustomIcons(array $icons): self
    {
        foreach ($icons as $icon) {
            $this->addCustomIcon($icon);
        }

        return $this;
    }

    /**
     * Register a named icon pack.
     *
     * @param  array<string>  $icons
     */
    public function registerIconPack(string $name, array $icons): self
    {
        $this->iconPacks[$name] = array_map(
            fn ($icon) => $this->normalizeIcon($icon),
            $icons
        );

        return $this;
    }

    /**
     * Set strict mode.
     *
     * In strict mode, only known icons are valid.
     * In non-strict mode (default), unknown icons generate warnings but are allowed.
     */
    public function setStrictMode(bool $strict): self
    {
        $this->strictMode = $strict;

        return $this;
    }

    /**
     * Enable or disable warning logging.
     */
    public function setLogWarnings(bool $log): self
    {
        $this->logWarnings = $log;

        return $this;
    }

    /**
     * Get all known solid icons.
     *
     * @return array<string>
     */
    public function getSolidIcons(): array
    {
        return self::SOLID_ICONS;
    }

    /**
     * Get all known brand icons.
     *
     * @return array<string>
     */
    public function getBrandIcons(): array
    {
        return self::BRAND_ICONS;
    }

    /**
     * Get all registered custom icons.
     *
     * @return array<string>
     */
    public function getCustomIcons(): array
    {
        return $this->customIcons;
    }

    /**
     * Get icon suggestions for a potentially misspelled icon.
     *
     * @return array<string>
     */
    public function getSuggestions(string $icon, int $maxSuggestions = 5): array
    {
        $normalized = $this->normalizeIcon($icon);
        $allIcons = array_merge(
            self::SOLID_ICONS,
            self::BRAND_ICONS,
            $this->customIcons
        );

        $suggestions = [];
        foreach ($allIcons as $knownIcon) {
            $distance = levenshtein($normalized, $knownIcon);
            if ($distance <= 3) { // Allow up to 3 character differences
                $suggestions[$knownIcon] = $distance;
            }
        }

        asort($suggestions);

        return array_slice(array_keys($suggestions), 0, $maxSuggestions);
    }
}
