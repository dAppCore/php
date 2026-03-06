<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Enums;

/**
 * Configuration scope types.
 *
 * Defines the hierarchy levels for config inheritance.
 * Resolution order: workspace → org → system (bottom-up)
 */
enum ScopeType: string
{
    case SYSTEM = 'system';       // Global defaults
    case ORG = 'org';             // Organisation level
    case WORKSPACE = 'workspace'; // Workspace (tenant) level

    /**
     * Get priority for resolution order.
     * Higher = more specific = checked first.
     */
    public function priority(): int
    {
        return match ($this) {
            self::SYSTEM => 0,
            self::ORG => 10,
            self::WORKSPACE => 20,
        };
    }

    /**
     * Get all scopes in resolution order (most specific first).
     *
     * @return array<ScopeType>
     */
    public static function resolutionOrder(): array
    {
        return [
            self::WORKSPACE,
            self::ORG,
            self::SYSTEM,
        ];
    }
}
