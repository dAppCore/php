<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Components;

/**
 * Navigation list component builder.
 *
 * @example
 * NavList::make()
 *     ->heading('Menu')
 *     ->item('Dashboard', '/hub')
 *     ->item('Settings', '/hub/settings', active: true)
 *     ->divider()
 *     ->item('Logout', '/logout')
 */
class NavList extends Component
{
    protected ?string $heading = null;

    protected array $items = [];

    /**
     * Set the navigation list heading.
     */
    public function heading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * Add a navigation item.
     */
    public function item(string $label, string $href = '#', bool $active = false, ?string $icon = null): static
    {
        $this->items[] = [
            'type' => 'item',
            'label' => $label,
            'href' => $href,
            'active' => $active,
            'icon' => $icon,
        ];

        return $this;
    }

    /**
     * Add a divider between items.
     */
    public function divider(): static
    {
        $this->items[] = ['type' => 'divider'];

        return $this;
    }

    /**
     * Add multiple items at once.
     *
     * @param  array<array{label: string, href?: string, active?: bool, icon?: string}>  $items
     */
    public function items(array $items): static
    {
        foreach ($items as $item) {
            if (is_string($item)) {
                $this->item($item);
            } else {
                $this->item(
                    label: $item['label'],
                    href: $item['href'] ?? '#',
                    active: $item['active'] ?? false,
                    icon: $item['icon'] ?? null
                );
            }
        }

        return $this;
    }

    /**
     * Render the navigation list to HTML.
     */
    public function render(): string
    {
        $attrs = $this->buildAttributes(['navlist']);

        $html = '<nav'.$attrs.'>';

        if ($this->heading !== null) {
            $html .= '<h4 class="px-3 py-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">'.e($this->heading).'</h4>';
        }

        $html .= '<ul class="space-y-1">';

        foreach ($this->items as $item) {
            if ($item['type'] === 'divider') {
                $html .= '<li class="my-2 border-t dark:border-zinc-700"></li>';

                continue;
            }

            $activeClass = $item['active'] ? 'bg-zinc-100 dark:bg-zinc-700' : '';
            $html .= '<li>';
            $html .= '<a href="'.e($item['href']).'" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700 '.$activeClass.'">';

            if ($item['icon']) {
                $html .= '<span class="w-5 h-5">'.$item['icon'].'</span>';
            }

            $html .= '<span>'.e($item['label']).'</span>';
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }
}
