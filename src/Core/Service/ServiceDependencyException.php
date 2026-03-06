<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service;

/**
 * Exception thrown when service dependencies cannot be resolved.
 *
 * This exception is thrown in the following scenarios:
 *
 * - Circular dependency detected between services
 * - Required dependency is missing
 * - Dependency version constraint not satisfied
 */
class ServiceDependencyException extends \RuntimeException
{
    /**
     * @var array<string>
     */
    protected array $dependencyChain = [];

    /**
     * Create exception for circular dependency.
     *
     * @param  array<string>  $chain  The chain of services that form the cycle
     */
    public static function circular(array $chain): self
    {
        $exception = new self(
            'Circular dependency detected: '.implode(' -> ', $chain)
        );
        $exception->dependencyChain = $chain;

        return $exception;
    }

    /**
     * Create exception for missing required dependency.
     */
    public static function missing(string $service, string $dependency): self
    {
        return new self(
            "Service '{$service}' requires '{$dependency}' which is not available"
        );
    }

    /**
     * Create exception for version constraint not satisfied.
     */
    public static function versionMismatch(
        string $service,
        string $dependency,
        string $required,
        string $available
    ): self {
        return new self(
            "Service '{$service}' requires '{$dependency}' {$required}, but {$available} is installed"
        );
    }

    /**
     * Get the dependency chain that caused the error.
     *
     * @return array<string>
     */
    public function getDependencyChain(): array
    {
        return $this->dependencyChain;
    }
}
