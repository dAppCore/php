<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\TranslationMemory\Contracts;

use Core\Lang\TranslationMemory\TranslationMemoryEntry;
use Illuminate\Support\Collection;

/**
 * Translation Memory Repository Contract.
 *
 * Defines the interface for translation memory storage backends.
 * Implementations may use JSON files, databases, or external services.
 */
interface TranslationMemoryRepository
{
    /**
     * Store a translation memory entry.
     *
     * @param  TranslationMemoryEntry  $entry  The entry to store
     * @return bool True if stored successfully
     */
    public function store(TranslationMemoryEntry $entry): bool;

    /**
     * Store multiple entries at once.
     *
     * @param  iterable<TranslationMemoryEntry>  $entries
     * @return int Number of entries stored
     */
    public function storeBatch(iterable $entries): int;

    /**
     * Find an exact match for the source text.
     *
     * @param  string  $source  Source text to find
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return TranslationMemoryEntry|null The matching entry or null
     */
    public function findExact(string $source, string $sourceLocale, string $targetLocale): ?TranslationMemoryEntry;

    /**
     * Find entries by ID.
     *
     * @param  string  $id  Entry ID
     */
    public function findById(string $id): ?TranslationMemoryEntry;

    /**
     * Find all entries for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function findByLocalePair(string $sourceLocale, string $targetLocale): Collection;

    /**
     * Search entries containing the given text.
     *
     * @param  string  $query  Search query
     * @param  string|null  $sourceLocale  Optional source locale filter
     * @param  string|null  $targetLocale  Optional target locale filter
     * @param  int  $limit  Maximum results to return
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function search(string $query, ?string $sourceLocale = null, ?string $targetLocale = null, int $limit = 50): Collection;

    /**
     * Get all entries.
     *
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function all(): Collection;

    /**
     * Delete an entry by ID.
     *
     * @param  string  $id  Entry ID
     * @return bool True if deleted
     */
    public function delete(string $id): bool;

    /**
     * Delete all entries for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return int Number of entries deleted
     */
    public function deleteByLocalePair(string $sourceLocale, string $targetLocale): int;

    /**
     * Delete all entries.
     *
     * @return int Number of entries deleted
     */
    public function deleteAll(): int;

    /**
     * Get the total count of entries.
     *
     * @param  string|null  $sourceLocale  Optional source locale filter
     * @param  string|null  $targetLocale  Optional target locale filter
     */
    public function count(?string $sourceLocale = null, ?string $targetLocale = null): int;

    /**
     * Get available locale pairs.
     *
     * @return array<array{source: string, target: string}>
     */
    public function getLocalePairs(): array;

    /**
     * Get statistics about the translation memory.
     *
     * @return array{
     *     total_entries: int,
     *     locale_pairs: int,
     *     avg_quality: float,
     *     high_quality_count: int,
     *     needs_review_count: int,
     *     total_usage: int,
     * }
     */
    public function getStats(): array;

    /**
     * Increment the usage count for an entry.
     *
     * @param  string  $id  Entry ID
     * @return bool True if incremented
     */
    public function incrementUsage(string $id): bool;

    /**
     * Update an entry's quality score.
     *
     * @param  string  $id  Entry ID
     * @param  float  $quality  New quality score
     * @return bool True if updated
     */
    public function updateQuality(string $id, float $quality): bool;
}
