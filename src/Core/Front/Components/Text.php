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
 * Text component builder.
 *
 * @example
 * Text::make('Hello world')->muted()
 * Text::make()->content('Paragraph text')->p()
 */
class Text extends Component
{
    protected string $content = '';

    protected string $tag = 'span';

    protected string $variant = 'default';

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * Create with initial content.
     */
    public static function make(string $content = ''): static
    {
        return new static($content);
    }

    /**
     * Set the text content.
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Render as a paragraph.
     */
    public function p(): static
    {
        $this->tag = 'p';

        return $this;
    }

    /**
     * Render as a span.
     */
    public function span(): static
    {
        $this->tag = 'span';

        return $this;
    }

    /**
     * Render as a div.
     */
    public function div(): static
    {
        $this->tag = 'div';

        return $this;
    }

    /**
     * Default text styling.
     */
    public function default(): static
    {
        $this->variant = 'default';

        return $this;
    }

    /**
     * Muted/subtle text.
     */
    public function muted(): static
    {
        $this->variant = 'muted';

        return $this;
    }

    /**
     * Success text (green).
     */
    public function success(): static
    {
        $this->variant = 'success';

        return $this;
    }

    /**
     * Warning text (amber).
     */
    public function warning(): static
    {
        $this->variant = 'warning';

        return $this;
    }

    /**
     * Error text (red).
     */
    public function error(): static
    {
        $this->variant = 'error';

        return $this;
    }

    /**
     * Get variant CSS classes.
     */
    protected function variantClasses(): array
    {
        return match ($this->variant) {
            'muted' => ['text-zinc-500', 'dark:text-zinc-400'],
            'success' => ['text-green-600', 'dark:text-green-400'],
            'warning' => ['text-amber-600', 'dark:text-amber-400'],
            'error' => ['text-red-600', 'dark:text-red-400'],
            default => ['text-zinc-900', 'dark:text-zinc-100'],
        };
    }

    /**
     * Render the text to HTML.
     */
    public function render(): string
    {
        $attrs = $this->buildAttributes($this->variantClasses());

        return '<'.$this->tag.$attrs.'>'.e($this->content).'</'.$this->tag.'>';
    }
}
