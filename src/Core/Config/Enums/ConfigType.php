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
 * Configuration value types.
 *
 * Determines how values are cast and validated.
 */
enum ConfigType: string
{
    case STRING = 'string';
    case BOOL = 'bool';
    case INT = 'int';
    case FLOAT = 'float';
    case ARRAY = 'array';
    case JSON = 'json';

    /**
     * Cast a value to this type.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::STRING => (string) $value,
            self::BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::INT => (int) $value,
            self::FLOAT => (float) $value,
            self::ARRAY => is_array($value) ? $value : json_decode($value, true) ?? [],
            self::JSON => is_string($value) ? json_decode($value, true) : $value,
        };
    }

    /**
     * Get default value for this type.
     */
    public function default(): mixed
    {
        return match ($this) {
            self::STRING => '',
            self::BOOL => false,
            self::INT => 0,
            self::FLOAT => 0.0,
            self::ARRAY, self::JSON => [],
        };
    }
}
