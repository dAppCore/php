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
 * Card component builder.
 *
 * @example
 * Card::make()
 *     ->title('Settings')
 *     ->body('Configure your preferences')
 *     ->action(Button::make()->label('Save'))
 */
class Card extends Component
{
    protected mixed $title = null;

    protected mixed $description = null;

    protected array $body = [];

    protected array $actions = [];

    /**
     * Set the card title.
     */
    public function title(mixed $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the card description (subtitle under title).
     */
    public function description(mixed $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Add content to the card body.
     */
    public function body(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->body[] = $item;
        }

        return $this;
    }

    /**
     * Add action buttons/links to the card footer.
     */
    public function actions(mixed ...$items): static
    {
        foreach ($items as $item) {
            $this->actions[] = $item;
        }

        return $this;
    }

    /**
     * Render the card to HTML.
     */
    public function render(): string
    {
        $attrs = $this->buildAttributes(['card', 'rounded-lg', 'border', 'bg-white', 'dark:bg-zinc-800']);

        $html = '<div'.$attrs.'>';

        // Header (title + description)
        if ($this->title !== null || $this->description !== null) {
            $html .= '<div class="card-header px-4 py-3 border-b dark:border-zinc-700">';
            if ($this->title !== null) {
                $html .= '<h3 class="text-lg font-semibold">'.$this->resolve($this->title).'</h3>';
            }
            if ($this->description !== null) {
                $html .= '<p class="text-sm text-zinc-500 dark:text-zinc-400">'.$this->resolve($this->description).'</p>';
            }
            $html .= '</div>';
        }

        // Body
        if (! empty($this->body)) {
            $html .= '<div class="card-body px-4 py-3">';
            foreach ($this->body as $item) {
                $html .= $this->raw($item);
            }
            $html .= '</div>';
        }

        // Actions
        if (! empty($this->actions)) {
            $html .= '<div class="card-actions px-4 py-3 border-t dark:border-zinc-700 flex gap-2 justify-end">';
            foreach ($this->actions as $action) {
                $html .= $this->raw($action);
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
