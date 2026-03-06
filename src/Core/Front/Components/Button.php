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
 * Button component builder.
 *
 * @example
 * Button::make()->label('Save')->primary()
 * Button::make()->label('Cancel')->secondary()->href('/back')
 */
class Button extends Component
{
    protected string $label = '';

    protected ?string $href = null;

    protected string $type = 'button';

    protected string $variant = 'primary';

    protected string $size = 'md';

    protected bool $disabled = false;

    /**
     * Set the button label.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Make this a link button.
     */
    public function href(string $href): static
    {
        $this->href = $href;

        return $this;
    }

    /**
     * Set the button type (button, submit, reset).
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set to submit type.
     */
    public function submit(): static
    {
        return $this->type('submit');
    }

    /**
     * Primary variant (default).
     */
    public function primary(): static
    {
        $this->variant = 'primary';

        return $this;
    }

    /**
     * Secondary variant.
     */
    public function secondary(): static
    {
        $this->variant = 'secondary';

        return $this;
    }

    /**
     * Danger variant.
     */
    public function danger(): static
    {
        $this->variant = 'danger';

        return $this;
    }

    /**
     * Ghost variant (minimal styling).
     */
    public function ghost(): static
    {
        $this->variant = 'ghost';

        return $this;
    }

    /**
     * Set size (sm, md, lg).
     */
    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Small size.
     */
    public function sm(): static
    {
        return $this->size('sm');
    }

    /**
     * Large size.
     */
    public function lg(): static
    {
        return $this->size('lg');
    }

    /**
     * Disable the button.
     */
    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Get variant CSS classes.
     */
    protected function variantClasses(): string
    {
        return match ($this->variant) {
            'primary' => 'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100',
            'secondary' => 'bg-zinc-100 text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-white dark:hover:bg-zinc-600',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            'ghost' => 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800',
            default => '',
        };
    }

    /**
     * Get size CSS classes.
     */
    protected function sizeClasses(): string
    {
        return match ($this->size) {
            'sm' => 'px-2.5 py-1.5 text-sm',
            'lg' => 'px-5 py-3 text-lg',
            default => 'px-4 py-2',
        };
    }

    /**
     * Render the button to HTML.
     */
    public function render(): string
    {
        $baseClasses = [
            'inline-flex',
            'items-center',
            'justify-center',
            'gap-2',
            'rounded-md',
            'font-medium',
            'transition-colors',
            'focus:outline-none',
            'focus:ring-2',
            'focus:ring-offset-2',
        ];

        if ($this->disabled) {
            $baseClasses[] = 'opacity-50';
            $baseClasses[] = 'cursor-not-allowed';
        }

        $classes = array_merge(
            $baseClasses,
            explode(' ', $this->variantClasses()),
            explode(' ', $this->sizeClasses())
        );

        // Link button
        if ($this->href !== null) {
            $attrs = $this->buildAttributes($classes);

            return '<a href="'.e($this->href).'"'.$attrs.'>'.e($this->label).'</a>';
        }

        // Button element
        if ($this->disabled) {
            $this->attr('disabled', true);
        }
        $this->attr('type', $this->type);
        $attrs = $this->buildAttributes($classes);

        return '<button'.$attrs.'>'.e($this->label).'</button>';
    }
}
