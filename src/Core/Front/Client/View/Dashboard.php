<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Client\View;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Client dashboard - namespace owner home.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('client::dashboard')
            ->layout('client::layouts.app');
    }
}
