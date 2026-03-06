<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

/**
 * Config import result.
 *
 * Tracks the outcome of a config import operation including
 * created, updated, skipped items, and any errors.
 *
 * @see ConfigExporter For import/export operations
 */
class ImportResult
{
    /**
     * Items created during import.
     *
     * @var array<array{code: string, type: string}>
     */
    protected array $created = [];

    /**
     * Items updated during import.
     *
     * @var array<array{code: string, type: string}>
     */
    protected array $updated = [];

    /**
     * Items skipped during import.
     *
     * @var array<string>
     */
    protected array $skipped = [];

    /**
     * Errors encountered during import.
     *
     * @var array<string>
     */
    protected array $errors = [];

    /**
     * Add a created item.
     *
     * @param  string  $code  The item code/identifier
     * @param  string  $type  The item type (key, value)
     */
    public function addCreated(string $code, string $type): void
    {
        $this->created[] = ['code' => $code, 'type' => $type];
    }

    /**
     * Add an updated item.
     *
     * @param  string  $code  The item code/identifier
     * @param  string  $type  The item type (key, value)
     */
    public function addUpdated(string $code, string $type): void
    {
        $this->updated[] = ['code' => $code, 'type' => $type];
    }

    /**
     * Add a skipped item.
     *
     * @param  string  $reason  Reason for skipping
     */
    public function addSkipped(string $reason): void
    {
        $this->skipped[] = $reason;
    }

    /**
     * Add an error.
     *
     * @param  string  $message  Error message
     */
    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Get created items.
     *
     * @return array<array{code: string, type: string}>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * Get updated items.
     *
     * @return array<array{code: string, type: string}>
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * Get skipped items.
     *
     * @return array<string>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * Get errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if import was successful (no errors).
     */
    public function isSuccessful(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if any changes were made.
     */
    public function hasChanges(): bool
    {
        return ! empty($this->created) || ! empty($this->updated);
    }

    /**
     * Check if there were any errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get total count of created items.
     */
    public function createdCount(): int
    {
        return count($this->created);
    }

    /**
     * Get total count of updated items.
     */
    public function updatedCount(): int
    {
        return count($this->updated);
    }

    /**
     * Get total count of skipped items.
     */
    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    /**
     * Get total count of errors.
     */
    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get summary string.
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->createdCount() > 0) {
            $parts[] = "{$this->createdCount()} created";
        }

        if ($this->updatedCount() > 0) {
            $parts[] = "{$this->updatedCount()} updated";
        }

        if ($this->skippedCount() > 0) {
            $parts[] = "{$this->skippedCount()} skipped";
        }

        if ($this->errorCount() > 0) {
            $parts[] = "{$this->errorCount()} errors";
        }

        if (empty($parts)) {
            return 'No changes';
        }

        return implode(', ', $parts);
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccessful(),
            'summary' => $this->getSummary(),
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'counts' => [
                'created' => $this->createdCount(),
                'updated' => $this->updatedCount(),
                'skipped' => $this->skippedCount(),
                'errors' => $this->errorCount(),
            ],
        ];
    }
}
