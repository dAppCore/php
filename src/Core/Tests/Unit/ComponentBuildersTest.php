<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Front\Components\Button;
use Core\Front\Components\Card;
use Core\Front\Components\Heading;
use Core\Front\Components\Layout;
use Core\Front\Components\NavList;
use Core\Front\Components\Text;

describe('Component Builders', function () {
    it('Card renders with title and body', function () {
        $card = Card::make()
            ->title('Settings')
            ->body('Configure your preferences');

        $html = $card->render();

        expect($html)
            ->toContain('Settings')
            ->toContain('Configure your preferences')
            ->toContain('class="card');
    });

    it('Card renders with actions', function () {
        $card = Card::make()
            ->title('Confirm')
            ->body('Are you sure?')
            ->actions(
                Button::make()->label('Cancel')->secondary(),
                Button::make()->label('Confirm')->primary()
            );

        $html = $card->render();

        expect($html)
            ->toContain('Cancel')
            ->toContain('Confirm')
            ->toContain('card-actions');
    });

    it('Button renders primary variant', function () {
        $button = Button::make()->label('Save')->primary();

        $html = $button->render();

        expect($html)
            ->toContain('Save')
            ->toContain('<button')
            ->toContain('bg-zinc-900');
    });

    it('Button renders as link when href set', function () {
        $button = Button::make()->label('Back')->href('/dashboard');

        $html = $button->render();

        expect($html)
            ->toContain('<a href="/dashboard"')
            ->toContain('Back');
    });

    it('NavList renders items', function () {
        $nav = NavList::make()
            ->heading('Menu')
            ->item('Dashboard', '/hub')
            ->item('Settings', '/hub/settings', active: true);

        $html = $nav->render();

        expect($html)
            ->toContain('Menu')
            ->toContain('Dashboard')
            ->toContain('href="/hub"')
            ->toContain('Settings')
            ->toContain('bg-zinc-100'); // active class
    });

    it('NavList renders dividers', function () {
        $nav = NavList::make()
            ->item('Home', '/')
            ->divider()
            ->item('Logout', '/logout');

        $html = $nav->render();

        expect($html)
            ->toContain('border-t');
    });

    it('Text renders with variants', function () {
        $muted = Text::make('Subtle text')->muted();
        $error = Text::make('Error message')->error();

        expect($muted->render())
            ->toContain('text-zinc-500');

        expect($error->render())
            ->toContain('text-red-600');
    });

    it('Heading renders with levels', function () {
        $h1 = Heading::make('Page Title')->h1();
        $h3 = Heading::make('Section')->h3();

        expect($h1->render())
            ->toContain('<h1')
            ->toContain('text-3xl');

        expect($h3->render())
            ->toContain('<h3')
            ->toContain('text-xl');
    });

    it('Heading renders with description', function () {
        $heading = Heading::make('Dashboard')
            ->h2()
            ->description('Overview of your account');

        $html = $heading->render();

        expect($html)
            ->toContain('Dashboard')
            ->toContain('Overview of your account')
            ->toContain('<p class="mt-1');
    });
});

describe('Layout Composition', function () {
    it('Layout renders with component builders', function () {
        $layout = Layout::make('HLCF')
            ->h(Heading::make('Dashboard')->h1())
            ->l(NavList::make()
                ->item('Home', '/')
                ->item('Settings', '/settings'))
            ->c(Card::make()
                ->title('Welcome')
                ->body('Your dashboard content here'))
            ->f(Text::make('Footer text')->muted());

        $html = $layout->render();

        expect($html)
            ->toContain('Dashboard')
            ->toContain('Home')
            ->toContain('Settings')
            ->toContain('Welcome')
            ->toContain('Footer text')
            ->toContain('data-layout="root"')
            ->toContain('data-slot="H"')
            ->toContain('data-slot="L"')
            ->toContain('data-slot="C"')
            ->toContain('data-slot="F"');
    });

    it('Layout nests with component builders', function () {
        $outer = Layout::make('HCF')
            ->h(Heading::make('App Header')->h1())
            ->c(Layout::make('LCR')
                ->l(NavList::make()->item('Nav', '/'))
                ->c(Card::make()->title('Main Content'))
                ->r(Card::make()->title('Sidebar')))
            ->f(Text::make('Copyright 2024'));

        $html = $outer->render();

        // Check nesting preserved
        expect($html)
            ->toContain('App Header')
            ->toContain('Main Content')
            ->toContain('Sidebar')
            ->toContain('data-layout="C"'); // Nested layout in C slot
    });

    it('components implement Htmlable interface', function () {
        $card = Card::make()->title('Test');
        $button = Button::make()->label('Click');
        $text = Text::make('Hello');

        // All should work with toHtml()
        expect($card->toHtml())->toContain('Test');
        expect($button->toHtml())->toContain('Click');
        expect($text->toHtml())->toContain('Hello');

        // All should work with string cast
        expect((string) $card)->toContain('Test');
        expect((string) $button)->toContain('Click');
        expect((string) $text)->toContain('Hello');
    });

    it('components support custom attributes', function () {
        $card = Card::make()
            ->title('Test')
            ->id('my-card')
            ->class('custom-class')
            ->attr('data-testid', 'card-1');

        $html = $card->render();

        expect($html)
            ->toContain('id="my-card"')
            ->toContain('custom-class')
            ->toContain('data-testid="card-1"');
    });
});
