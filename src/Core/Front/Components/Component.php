<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Base component class for data-driven UI composition.
 *
 * Provides fluent interface for building HTML components programmatically.
 * Used by MCP tools and agents to compose UIs without Blade templates.
 */
abstract class Component implements Htmlable
{
    protected array $attributes = [];

    protected array $classes = [];

    /**
     * Create a new component instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set an HTML attribute.
     */
    public function attr(string $key, mixed $value = true): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set multiple attributes.
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Add CSS classes.
     */
    public function class(string ...$classes): static
    {
        $this->classes = array_merge($this->classes, $classes);

        return $this;
    }

    /**
     * Set the ID attribute.
     */
    public function id(string $id): static
    {
        return $this->attr('id', $id);
    }

    /**
     * Build the attributes string for HTML output.
     */
    protected function buildAttributes(array $extraClasses = []): string
    {
        $attrs = $this->attributes;
        $allClasses = array_merge($this->classes, $extraClasses);

        if (! empty($allClasses)) {
            $existing = $attrs['class'] ?? '';
            $attrs['class'] = trim($existing.' '.implode(' ', array_unique($allClasses)));
        }

        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = e($key);
            } elseif ($value !== false && $value !== null && $value !== '') {
                $parts[] = e($key).'="'.e($value).'"';
            }
        }

        return $parts ? ' '.implode(' ', $parts) : '';
    }

    /**
     * Resolve content to string.
     */
    protected function resolve(mixed $content): string
    {
        if ($content === null) {
            return '';
        }

        if ($content instanceof Htmlable) {
            return $content->toHtml();
        }

        if ($content instanceof Closure) {
            return $this->resolve($content());
        }

        if (is_array($content)) {
            return implode('', array_map(fn ($item) => $this->resolve($item), $content));
        }

        return e((string) $content);
    }

    /**
     * Resolve content without escaping (for raw HTML).
     */
    protected function raw(mixed $content): string
    {
        if ($content === null) {
            return '';
        }

        if ($content instanceof Htmlable) {
            return $content->toHtml();
        }

        if ($content instanceof Closure) {
            return $this->raw($content());
        }

        if (is_array($content)) {
            return implode('', array_map(fn ($item) => $this->raw($item), $content));
        }

        return (string) $content;
    }

    /**
     * Render the component to HTML.
     */
    abstract public function render(): string;

    /**
     * Get the HTML string (Htmlable interface).
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
