<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Database\Seeders\Exceptions;

use RuntimeException;

/**
 * Thrown when a circular dependency is detected in seeder ordering.
 *
 * This exception indicates that the seeder dependency graph contains a cycle,
 * making it impossible to determine a valid execution order.
 */
class CircularDependencyException extends RuntimeException
{
    /**
     * The seeders involved in the circular dependency.
     *
     * @var array<string>
     */
    public readonly array $cycle;

    /**
     * Create a new exception instance.
     *
     * @param  array<string>  $cycle  The seeders forming the dependency cycle
     */
    public function __construct(array $cycle)
    {
        $this->cycle = $cycle;

        $cycleStr = implode(' -> ', $cycle);

        parent::__construct(
            "Circular dependency detected in seeders: {$cycleStr}"
        );
    }

    /**
     * Create an exception from a dependency path.
     *
     * @param  array<string>  $path  The path of seeders leading to the cycle
     * @param  string  $duplicate  The seeder that was found again, completing the cycle
     */
    public static function fromPath(array $path, string $duplicate): self
    {
        // Find where the cycle starts
        $cycleStart = array_search($duplicate, $path, true);
        $cycle = array_slice($path, $cycleStart);
        $cycle[] = $duplicate;

        return new self($cycle);
    }
}
