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
 * Config version difference.
 *
 * Represents the difference between two config versions,
 * tracking added, removed, and changed values.
 *
 * @see ConfigVersioning For version comparison
 */
class VersionDiff
{
    /**
     * Keys added in the new version.
     *
     * @var array<array{key: string, value: mixed}>
     */
    protected array $added = [];

    /**
     * Keys removed in the new version.
     *
     * @var array<array{key: string, value: mixed}>
     */
    protected array $removed = [];

    /**
     * Keys with changed values.
     *
     * @var array<array{key: string, old: mixed, new: mixed}>
     */
    protected array $changed = [];

    /**
     * Keys with changed lock status.
     *
     * @var array<array{key: string, old: bool, new: bool}>
     */
    protected array $lockChanged = [];

    /**
     * Add an added key.
     *
     * @param  string  $key  The config key
     * @param  mixed  $value  The new value
     */
    public function addAdded(string $key, mixed $value): void
    {
        $this->added[] = ['key' => $key, 'value' => $value];
    }

    /**
     * Add a removed key.
     *
     * @param  string  $key  The config key
     * @param  mixed  $value  The old value
     */
    public function addRemoved(string $key, mixed $value): void
    {
        $this->removed[] = ['key' => $key, 'value' => $value];
    }

    /**
     * Add a changed key.
     *
     * @param  string  $key  The config key
     * @param  mixed  $oldValue  The old value
     * @param  mixed  $newValue  The new value
     */
    public function addChanged(string $key, mixed $oldValue, mixed $newValue): void
    {
        $this->changed[] = ['key' => $key, 'old' => $oldValue, 'new' => $newValue];
    }

    /**
     * Add a lock status change.
     *
     * @param  string  $key  The config key
     * @param  bool  $oldLocked  Old lock status
     * @param  bool  $newLocked  New lock status
     */
    public function addLockChanged(string $key, bool $oldLocked, bool $newLocked): void
    {
        $this->lockChanged[] = ['key' => $key, 'old' => $oldLocked, 'new' => $newLocked];
    }

    /**
     * Get added keys.
     *
     * @return array<array{key: string, value: mixed}>
     */
    public function getAdded(): array
    {
        return $this->added;
    }

    /**
     * Get removed keys.
     *
     * @return array<array{key: string, value: mixed}>
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }

    /**
     * Get changed keys.
     *
     * @return array<array{key: string, old: mixed, new: mixed}>
     */
    public function getChanged(): array
    {
        return $this->changed;
    }

    /**
     * Get lock status changes.
     *
     * @return array<array{key: string, old: bool, new: bool}>
     */
    public function getLockChanged(): array
    {
        return $this->lockChanged;
    }

    /**
     * Check if there are any differences.
     */
    public function hasDifferences(): bool
    {
        return ! empty($this->added)
            || ! empty($this->removed)
            || ! empty($this->changed)
            || ! empty($this->lockChanged);
    }

    /**
     * Check if there are no differences.
     */
    public function isEmpty(): bool
    {
        return ! $this->hasDifferences();
    }

    /**
     * Get total count of differences.
     */
    public function count(): int
    {
        return count($this->added)
            + count($this->removed)
            + count($this->changed)
            + count($this->lockChanged);
    }

    /**
     * Get summary string.
     */
    public function getSummary(): string
    {
        if ($this->isEmpty()) {
            return 'No differences';
        }

        $parts = [];

        if (count($this->added) > 0) {
            $parts[] = count($this->added).' added';
        }

        if (count($this->removed) > 0) {
            $parts[] = count($this->removed).' removed';
        }

        if (count($this->changed) > 0) {
            $parts[] = count($this->changed).' changed';
        }

        if (count($this->lockChanged) > 0) {
            $parts[] = count($this->lockChanged).' lock changes';
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
            'has_differences' => $this->hasDifferences(),
            'summary' => $this->getSummary(),
            'added' => $this->added,
            'removed' => $this->removed,
            'changed' => $this->changed,
            'lock_changed' => $this->lockChanged,
            'counts' => [
                'added' => count($this->added),
                'removed' => count($this->removed),
                'changed' => count($this->changed),
                'lock_changed' => count($this->lockChanged),
                'total' => $this->count(),
            ],
        ];
    }
}
