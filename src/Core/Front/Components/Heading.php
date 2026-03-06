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
 * Heading component builder.
 *
 * @example
 * Heading::make('Dashboard')->h1()
 * Heading::make('Settings')->h2()->description('Configure your account')
 */
class Heading extends Component
{
    protected string $text = '';

    protected int $level = 2;

    protected ?string $description = null;

    public function __construct(string $text = '')
    {
        $this->text = $text;
    }

    /**
     * Create with initial text.
     */
    public static function make(string $text = ''): static
    {
        return new static($text);
    }

    /**
     * Set the heading text.
     */
    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Set heading level (1-6).
     */
    public function level(int $level): static
    {
        $this->level = max(1, min(6, $level));

        return $this;
    }

    /**
     * H1 heading.
     */
    public function h1(): static
    {
        return $this->level(1);
    }

    /**
     * H2 heading.
     */
    public function h2(): static
    {
        return $this->level(2);
    }

    /**
     * H3 heading.
     */
    public function h3(): static
    {
        return $this->level(3);
    }

    /**
     * H4 heading.
     */
    public function h4(): static
    {
        return $this->level(4);
    }

    /**
     * Add a description/subtitle.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get size classes based on level.
     */
    protected function sizeClasses(): array
    {
        return match ($this->level) {
            1 => ['text-3xl', 'font-bold', 'tracking-tight'],
            2 => ['text-2xl', 'font-semibold', 'tracking-tight'],
            3 => ['text-xl', 'font-semibold'],
            4 => ['text-lg', 'font-medium'],
            5 => ['text-base', 'font-medium'],
            6 => ['text-sm', 'font-medium', 'uppercase', 'tracking-wider'],
            default => ['text-xl', 'font-semibold'],
        };
    }

    /**
     * Render the heading to HTML.
     */
    public function render(): string
    {
        $tag = 'h'.$this->level;
        $classes = array_merge(
            $this->sizeClasses(),
            ['text-zinc-900', 'dark:text-zinc-100']
        );
        $attrs = $this->buildAttributes($classes);

        $html = '<'.$tag.$attrs.'>'.e($this->text).'</'.$tag.'>';

        if ($this->description !== null) {
            $html .= '<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">'.e($this->description).'</p>';
        }

        return $html;
    }
}
