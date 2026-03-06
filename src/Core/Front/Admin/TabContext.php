<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin;

/**
 * Shared context for tab components.
 *
 * Allows parent <admin:tabs> to communicate selected state
 * to child <admin:tab.panel> components without explicit prop passing.
 */
class TabContext
{
    public static ?string $selected = null;
}
