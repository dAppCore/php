<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\Support;

/**
 * Represents a group of related menu items with optional separator and header.
 *
 * Menu item groups allow organizing menu items within a navigation section
 * by adding visual separators and section headers. This improves menu
 * organization for modules with many related items.
 *
 * ## Features
 *
 * - Section headers with optional icons
 * - Visual separators between groups
 * - Collapsible group support
 * - Badge support for group headers
 *
 * ## Usage
 *
 * Groups are defined in the menu item structure using special keys:
 *
 * ```php
 * return [
 *     [
 *         'group' => 'services',
 *         'priority' => 50,
 *         'item' => fn() => [
 *             'label' => 'Commerce',
 *             'icon' => 'shopping-cart',
 *             'href' => route('hub.commerce.index'),
 *             'active' => request()->routeIs('hub.commerce.*'),
 *             // Define sub-groups within children
 *             'children' => [
 *                 // Group header (section)
 *                 MenuItemGroup::header('Products', 'cube'),
 *                 ['label' => 'All Products', 'href' => '/products'],
 *                 ['label' => 'Categories', 'href' => '/categories'],
 *                 // Separator
 *                 MenuItemGroup::separator(),
 *                 // Another group
 *                 MenuItemGroup::header('Orders', 'receipt'),
 *                 ['label' => 'All Orders', 'href' => '/orders'],
 *                 ['label' => 'Pending', 'href' => '/orders/pending'],
 *             ],
 *         ],
 *     ],
 * ];
 * ```
 */
class MenuItemGroup
{
    /**
     * Type constant for separator items.
     */
    public const TYPE_SEPARATOR = 'separator';

    /**
     * Type constant for section header items.
     */
    public const TYPE_HEADER = 'header';

    /**
     * Type constant for collapsible group items.
     */
    public const TYPE_COLLAPSIBLE = 'collapsible';

    /**
     * Create a separator element.
     *
     * Separators are visual dividers between groups of menu items.
     * They render as a horizontal line in the menu.
     *
     * @return array{separator: true}
     */
    public static function separator(): array
    {
        return ['separator' => true];
    }

    /**
     * Create a section header element.
     *
     * Section headers provide a label for a group of related menu items.
     * They appear as styled text (usually uppercase) with an optional icon.
     *
     * @param  string  $label  The header text
     * @param  string|null  $icon  Optional FontAwesome icon name
     * @param  string|null  $color  Optional color theme (e.g., 'blue', 'green')
     * @param  string|array|null  $badge  Optional badge text or config
     * @return array{section: string, icon?: string, color?: string, badge?: string|array}
     */
    public static function header(
        string $label,
        ?string $icon = null,
        ?string $color = null,
        string|array|null $badge = null
    ): array {
        $item = ['section' => $label];

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        if ($color !== null) {
            $item['color'] = $color;
        }

        if ($badge !== null) {
            $item['badge'] = $badge;
        }

        return $item;
    }

    /**
     * Create a collapsible group.
     *
     * Collapsible groups can be expanded/collapsed by clicking the header.
     * They maintain state using localStorage when configured.
     *
     * @param  string  $label  The group header text
     * @param  array<int, array>  $children  Child menu items
     * @param  string|null  $icon  Optional FontAwesome icon name
     * @param  string|null  $color  Optional color theme
     * @param  bool  $defaultOpen  Whether the group is open by default
     * @param  string|null  $stateKey  Optional localStorage key for persisting state
     * @return array{collapsible: true, label: string, children: array, icon?: string, color?: string, open?: bool, stateKey?: string}
     */
    public static function collapsible(
        string $label,
        array $children,
        ?string $icon = null,
        ?string $color = null,
        bool $defaultOpen = true,
        ?string $stateKey = null
    ): array {
        $item = [
            'collapsible' => true,
            'label' => $label,
            'children' => $children,
            'open' => $defaultOpen,
        ];

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        if ($color !== null) {
            $item['color'] = $color;
        }

        if ($stateKey !== null) {
            $item['stateKey'] = $stateKey;
        }

        return $item;
    }

    /**
     * Create a divider with an optional label.
     *
     * Dividers are similar to separators but can include centered text.
     *
     * @param  string|null  $label  Optional centered label
     * @return array{divider: true, label?: string}
     */
    public static function divider(?string $label = null): array
    {
        $item = ['divider' => true];

        if ($label !== null) {
            $item['label'] = $label;
        }

        return $item;
    }

    /**
     * Check if an item is a separator.
     *
     * @param  array  $item  The menu item to check
     */
    public static function isSeparator(array $item): bool
    {
        return ! empty($item['separator']);
    }

    /**
     * Check if an item is a section header.
     *
     * @param  array  $item  The menu item to check
     */
    public static function isHeader(array $item): bool
    {
        return ! empty($item['section']);
    }

    /**
     * Check if an item is a collapsible group.
     *
     * @param  array  $item  The menu item to check
     */
    public static function isCollapsible(array $item): bool
    {
        return ! empty($item['collapsible']);
    }

    /**
     * Check if an item is a divider.
     *
     * @param  array  $item  The menu item to check
     */
    public static function isDivider(array $item): bool
    {
        return ! empty($item['divider']);
    }

    /**
     * Check if an item is a structural element (separator, header, divider, or collapsible).
     *
     * @param  array  $item  The menu item to check
     */
    public static function isStructural(array $item): bool
    {
        return self::isSeparator($item)
            || self::isHeader($item)
            || self::isDivider($item)
            || self::isCollapsible($item);
    }

    /**
     * Check if an item is a regular menu link.
     *
     * @param  array  $item  The menu item to check
     */
    public static function isLink(array $item): bool
    {
        return ! self::isStructural($item) && isset($item['label']);
    }
}
