<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Front\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\View;

/**
 * HLCRF Layout Compositor - Data-driven layout builder
 *
 * Every website = nested HLCRF structures:
 * - H = Header
 * - L = Left sidebar
 * - C = Content
 * - R = Right sidebar
 * - F = Footer
 *
 * What's missing defines layout type. Nesting handles complexity.
 *
 * @example
 * Layout::make('HLCF')
 *     ->h('<nav>Logo</nav>')
 *     ->l(Sidebar::make()->items(['Dashboard', 'Settings']))
 *     ->c('<main>Content</main>')
 *     ->f('<footer>Links</footer>')
 *     ->render();
 */
class Layout implements Htmlable, Renderable
{
    protected string $variant;

    protected array $attributes = [];

    protected string $path = '';  // Hierarchical path (e.g., "L-" for nested in Left)

    protected array $header = [];

    protected array $left = [];

    protected array $content = [];

    protected array $right = [];

    protected array $footer = [];

    public function __construct(string $variant = 'HCF', string $path = '')
    {
        $this->variant = strtoupper($variant);
        $this->path = $path;
    }

    /**
     * Create a new layout instance
     */
    public static function make(string $variant = 'HCF', string $path = ''): static
    {
        return new static($variant, $path);
    }

    /**
     * Get the slot ID for a given slot letter
     */
    protected function slotId(string $slot): string
    {
        return $this->path.$slot;
    }

    /**
     * Add to the Header slot
     */
    public function h(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->header[] = $item;
        }

        return $this;
    }

    /**
     * Add to the Left slot
     */
    public function l(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->left[] = $item;
        }

        return $this;
    }

    /**
     * Add to the Content slot
     */
    public function c(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->content[] = $item;
        }

        return $this;
    }

    /**
     * Add to the Right slot
     */
    public function r(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->right[] = $item;
        }

        return $this;
    }

    /**
     * Add to the Footer slot
     */
    public function f(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->footer[] = $item;
        }

        return $this;
    }

    /**
     * Alias methods for readability (variadic)
     */
    public function addHeader(mixed ...$items): static
    {
        return $this->h(...$items);
    }

    public function addLeft(mixed ...$items): static
    {
        return $this->l(...$items);
    }

    public function addContent(mixed ...$items): static
    {
        return $this->c(...$items);
    }

    public function addRight(mixed ...$items): static
    {
        return $this->r(...$items);
    }

    public function addFooter(mixed ...$items): static
    {
        return $this->f(...$items);
    }

    /**
     * Set HTML attributes on the layout container
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Add a CSS class
     */
    public function class(string $class): static
    {
        $existing = $this->attributes['class'] ?? '';
        $this->attributes['class'] = trim($existing.' '.$class);

        return $this;
    }

    /**
     * Check if variant includes a slot
     */
    protected function has(string $slot): bool
    {
        return str_contains($this->variant, strtoupper($slot));
    }

    /**
     * Render all items in a slot with indexed data attributes
     */
    protected function renderSlot(array $items, string $slot): string
    {
        $html = '';
        foreach ($items as $index => $item) {
            $itemId = $this->slotId($slot).'-'.$index;
            $resolved = $this->resolveItem($item, $slot);
            $html .= '<div data-block="'.e($itemId).'">'.$resolved.'</div>';
        }

        return $html;
    }

    /**
     * Resolve a single item to string, passing path context to nested layouts
     */
    protected function resolveItem(mixed $content, string $slot): string
    {
        if ($content === null) {
            return '';
        }

        // Nested Layout - inject the path context
        if ($content instanceof Layout) {
            $content->path = $this->slotId($slot).'-';

            return $content->render();
        }

        if ($content instanceof Htmlable) {
            return $content->toHtml();
        }

        if ($content instanceof Renderable) {
            return $content->render();
        }

        if ($content instanceof View) {
            return $content->render();
        }

        if (is_callable($content)) {
            return $this->resolveItem($content(), $slot);
        }

        return (string) $content;
    }

    /**
     * Build attributes string
     */
    protected function buildAttributes(): string
    {
        $attrs = $this->attributes;
        $attrs['class'] = trim('hlcrf-layout '.($attrs['class'] ?? ''));

        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = $key;
            } elseif ($value !== false && $value !== null) {
                $parts[] = $key.'="'.e($value).'"';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Render the layout to HTML
     */
    public function render(): string
    {
        $layoutId = $this->path ? rtrim($this->path, '-') : 'root';
        $html = '<div '.$this->buildAttributes().' data-layout="'.e($layoutId).'">';

        // Header
        if ($this->has('H') && ! empty($this->header)) {
            $id = $this->slotId('H');
            $html .= '<header class="hlcrf-header" data-slot="'.e($id).'">'.$this->renderSlot($this->header, 'H').'</header>';
        }

        // Body (L, C, R)
        if ($this->has('L') || $this->has('C') || $this->has('R')) {
            $html .= '<div class="hlcrf-body flex flex-1">';

            if ($this->has('L') && ! empty($this->left)) {
                $id = $this->slotId('L');
                $html .= '<aside class="hlcrf-left shrink-0" data-slot="'.e($id).'">'.$this->renderSlot($this->left, 'L').'</aside>';
            }

            if ($this->has('C')) {
                $id = $this->slotId('C');
                $html .= '<main class="hlcrf-content flex-1" data-slot="'.e($id).'">'.$this->renderSlot($this->content, 'C').'</main>';
            }

            if ($this->has('R') && ! empty($this->right)) {
                $id = $this->slotId('R');
                $html .= '<aside class="hlcrf-right shrink-0" data-slot="'.e($id).'">'.$this->renderSlot($this->right, 'R').'</aside>';
            }

            $html .= '</div>';
        }

        // Footer
        if ($this->has('F') && ! empty($this->footer)) {
            $id = $this->slotId('F');
            $html .= '<footer class="hlcrf-footer" data-slot="'.e($id).'">'.$this->renderSlot($this->footer, 'F').'</footer>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get the HTML string
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Cast to string
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
