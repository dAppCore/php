<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Contracts;

/**
 * Represents a dependency on another service.
 *
 * Services can declare dependencies on other services using this class,
 * enabling the framework to validate service availability, resolve
 * dependencies in the correct order, and detect circular dependencies.
 *
 * ## Dependency Types
 *
 * - **Required**: Service cannot function without this dependency
 * - **Optional**: Service works with reduced functionality if absent
 *
 * ## Example Usage
 *
 * ```php
 * public static function dependencies(): array
 * {
 *     return [
 *         // Required dependency on auth service
 *         ServiceDependency::required('auth', '>=1.0.0'),
 *
 *         // Optional dependency on analytics
 *         ServiceDependency::optional('analytics'),
 *
 *         // Required with minimum version
 *         ServiceDependency::required('billing', '>=2.0.0', '<3.0.0'),
 *     ];
 * }
 * ```
 */
final readonly class ServiceDependency
{
    /**
     * @param  string  $serviceCode  The code of the required service
     * @param  bool  $required  Whether this dependency is required
     * @param  string|null  $minVersion  Minimum version constraint (e.g., ">=1.0.0")
     * @param  string|null  $maxVersion  Maximum version constraint (e.g., "<3.0.0")
     */
    public function __construct(
        public string $serviceCode,
        public bool $required = true,
        public ?string $minVersion = null,
        public ?string $maxVersion = null,
    ) {}

    /**
     * Create a required dependency.
     */
    public static function required(
        string $serviceCode,
        ?string $minVersion = null,
        ?string $maxVersion = null
    ): self {
        return new self($serviceCode, true, $minVersion, $maxVersion);
    }

    /**
     * Create an optional dependency.
     */
    public static function optional(
        string $serviceCode,
        ?string $minVersion = null,
        ?string $maxVersion = null
    ): self {
        return new self($serviceCode, false, $minVersion, $maxVersion);
    }

    /**
     * Check if a version satisfies this dependency's constraints.
     */
    public function satisfiedBy(string $version): bool
    {
        if ($this->minVersion !== null && ! $this->checkConstraint($version, $this->minVersion)) {
            return false;
        }

        if ($this->maxVersion !== null && ! $this->checkConstraint($version, $this->maxVersion)) {
            return false;
        }

        return true;
    }

    /**
     * Check a single version constraint.
     */
    protected function checkConstraint(string $version, string $constraint): bool
    {
        // Parse constraint (e.g., ">=1.0.0" or "<3.0.0")
        if (preg_match('/^([<>=!]+)?(.+)$/', $constraint, $matches)) {
            $operator = $matches[1] ?: '==';
            $constraintVersion = ltrim($matches[2], 'v');
            $version = ltrim($version, 'v');

            return version_compare($version, $constraintVersion, $operator);
        }

        return true;
    }

    /**
     * Get a human-readable description of the constraint.
     */
    public function getConstraintDescription(): string
    {
        $parts = [];

        if ($this->minVersion !== null) {
            $parts[] = $this->minVersion;
        }

        if ($this->maxVersion !== null) {
            $parts[] = $this->maxVersion;
        }

        if (empty($parts)) {
            return 'any version';
        }

        return implode(' ', $parts);
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'service_code' => $this->serviceCode,
            'required' => $this->required,
            'min_version' => $this->minVersion,
            'max_version' => $this->maxVersion,
        ], fn ($v) => $v !== null);
    }
}
