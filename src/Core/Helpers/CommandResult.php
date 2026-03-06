<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Represents the result of a remote command execution.
 */
class CommandResult
{
    public function __construct(
        public readonly string $output,
        public readonly int $exitCode = 0,
        public readonly ?string $error = null
    ) {}

    /**
     * Check if the command was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Check if the command failed.
     */
    public function isFailed(): bool
    {
        return ! $this->isSuccessful();
    }

    /**
     * Get the output lines as an array.
     */
    public function getLines(): array
    {
        return array_filter(explode("\n", $this->output));
    }

    /**
     * Get the first line of output.
     */
    public function getFirstLine(): ?string
    {
        $lines = $this->getLines();

        return $lines[0] ?? null;
    }

    /**
     * Check if output contains a string.
     */
    public function contains(string $needle): bool
    {
        return str_contains($this->output, $needle);
    }

    /**
     * Get the output as a string.
     */
    public function __toString(): string
    {
        return $this->output;
    }
}
