<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\TranslationMemory;

use Core\Lang\TranslationMemory\Contracts\TranslationMemoryRepository;
use Illuminate\Support\Collection;

/**
 * Translation Memory Service.
 *
 * Provides a unified interface for translation memory operations including:
 * - Storing and retrieving previous translations
 * - Suggesting translations for similar/matching strings
 * - Fuzzy matching for partial matches
 * - TMX import/export for interoperability
 * - Quality/confidence scoring
 *
 * Translation memory helps translators work more efficiently by:
 * 1. Reusing exact matches from previous translations
 * 2. Suggesting similar translations for fuzzy matches
 * 3. Maintaining consistency across translations
 * 4. Reducing translation time and costs
 *
 * Usage:
 *   $tm = app(TranslationMemory::class);
 *
 *   // Store a translation
 *   $tm->store('Hello', 'Hallo', 'en_GB', 'de_DE');
 *
 *   // Get exact match
 *   $translation = $tm->get('Hello', 'en_GB', 'de_DE');
 *
 *   // Get suggestions (fuzzy matching)
 *   $suggestions = $tm->suggest('Hello world', 'en_GB', 'de_DE');
 *
 * Configuration in config/core.php:
 *   'lang' => [
 *       'translation_memory' => [
 *           'enabled' => true,
 *           'storage_path' => storage_path('framework/translation-memory'),
 *           'driver' => 'json', // json, database
 *           'fuzzy' => [
 *               'min_similarity' => 0.6,
 *               'max_results' => 10,
 *               'algorithm' => 'combined',
 *           ],
 *       ],
 *   ]
 */
class TranslationMemory
{
    /**
     * The fuzzy matcher instance.
     */
    protected FuzzyMatcher $fuzzyMatcher;

    /**
     * The TMX importer instance.
     */
    protected TmxImporter $tmxImporter;

    /**
     * The TMX exporter instance.
     */
    protected TmxExporter $tmxExporter;

    /**
     * Create a new translation memory service.
     */
    public function __construct(
        protected TranslationMemoryRepository $repository,
    ) {
        $this->fuzzyMatcher = new FuzzyMatcher($repository);
        $this->tmxImporter = new TmxImporter($repository);
        $this->tmxExporter = new TmxExporter($repository);
    }

    /**
     * Store a translation in the memory.
     *
     * @param  string  $source  Source text
     * @param  string  $target  Translated text
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float  $quality  Quality score (0.0-1.0)
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @return TranslationMemoryEntry The stored entry
     */
    public function store(
        string $source,
        string $target,
        string $sourceLocale,
        string $targetLocale,
        float $quality = 1.0,
        array $metadata = [],
    ): TranslationMemoryEntry {
        $entry = new TranslationMemoryEntry(
            id: TranslationMemoryEntry::generateId($source, $sourceLocale, $targetLocale),
            sourceLocale: $sourceLocale,
            targetLocale: $targetLocale,
            source: $source,
            target: $target,
            quality: $quality,
            metadata: $metadata,
        );

        $this->repository->store($entry);

        return $entry;
    }

    /**
     * Store multiple translations at once.
     *
     * @param  array<array{source: string, target: string, source_locale?: string, target_locale?: string, quality?: float, metadata?: array}>  $translations
     * @param  string|null  $defaultSourceLocale  Default source locale
     * @param  string|null  $defaultTargetLocale  Default target locale
     * @return int Number of entries stored
     */
    public function storeBatch(
        array $translations,
        ?string $defaultSourceLocale = null,
        ?string $defaultTargetLocale = null,
    ): int {
        $entries = [];

        foreach ($translations as $translation) {
            $sourceLocale = $translation['source_locale'] ?? $defaultSourceLocale ?? 'en';
            $targetLocale = $translation['target_locale'] ?? $defaultTargetLocale ?? 'en';

            $entries[] = new TranslationMemoryEntry(
                id: TranslationMemoryEntry::generateId($translation['source'], $sourceLocale, $targetLocale),
                sourceLocale: $sourceLocale,
                targetLocale: $targetLocale,
                source: $translation['source'],
                target: $translation['target'],
                quality: $translation['quality'] ?? 1.0,
                metadata: $translation['metadata'] ?? [],
            );
        }

        return $this->repository->storeBatch($entries);
    }

    /**
     * Get an exact match translation.
     *
     * @param  string  $source  Source text to translate
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return string|null The translation or null if not found
     */
    public function get(string $source, string $sourceLocale, string $targetLocale): ?string
    {
        $entry = $this->repository->findExact($source, $sourceLocale, $targetLocale);

        if ($entry === null) {
            return null;
        }

        // Increment usage count
        $this->repository->incrementUsage($entry->getId());

        return $entry->getTarget();
    }

    /**
     * Get an exact match entry with full metadata.
     *
     * @param  string  $source  Source text
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     */
    public function getEntry(string $source, string $sourceLocale, string $targetLocale): ?TranslationMemoryEntry
    {
        return $this->repository->findExact($source, $sourceLocale, $targetLocale);
    }

    /**
     * Suggest translations using fuzzy matching.
     *
     * Returns similar translations when an exact match is not found.
     *
     * @param  string  $source  Source text to translate
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity threshold (0.0-1.0)
     * @param  int|null  $maxResults  Maximum number of suggestions
     * @return Collection<int, array{entry: TranslationMemoryEntry, similarity: float, confidence: float}>
     */
    public function suggest(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
        ?int $maxResults = null,
    ): Collection {
        return $this->fuzzyMatcher->findSimilar(
            $source,
            $sourceLocale,
            $targetLocale,
            $minSimilarity,
            $maxResults,
        );
    }

    /**
     * Get the best translation match (exact or fuzzy).
     *
     * First checks for an exact match, then falls back to fuzzy matching.
     *
     * @param  string  $source  Source text to translate
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity for fuzzy match
     * @return array{translation: string, similarity: float, confidence: float, is_exact: bool}|null
     */
    public function getBestMatch(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
    ): ?array {
        $match = $this->fuzzyMatcher->getBestMatch($source, $sourceLocale, $targetLocale, $minSimilarity);

        if ($match === null) {
            return null;
        }

        // Increment usage for exact matches
        if ($match['similarity'] >= 1.0) {
            $this->repository->incrementUsage($match['entry']->getId());
        }

        return [
            'translation' => $match['entry']->getTarget(),
            'similarity' => $match['similarity'],
            'confidence' => $match['confidence'],
            'is_exact' => $match['similarity'] >= 1.0,
        ];
    }

    /**
     * Translate with automatic matching.
     *
     * Returns the best available translation or the original text.
     *
     * @param  string  $source  Source text to translate
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity threshold
     * @return array{text: string, matched: bool, similarity: float|null}
     */
    public function translate(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
    ): array {
        $match = $this->getBestMatch($source, $sourceLocale, $targetLocale, $minSimilarity);

        if ($match === null) {
            return [
                'text' => $source,
                'matched' => false,
                'similarity' => null,
            ];
        }

        return [
            'text' => $match['translation'],
            'matched' => true,
            'similarity' => $match['similarity'],
        ];
    }

    /**
     * Update the quality score for a translation.
     *
     * @param  string  $source  Source text
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float  $quality  New quality score (0.0-1.0)
     * @return bool True if updated
     */
    public function updateQuality(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        float $quality,
    ): bool {
        $entry = $this->repository->findExact($source, $sourceLocale, $targetLocale);

        if ($entry === null) {
            return false;
        }

        return $this->repository->updateQuality($entry->getId(), $quality);
    }

    /**
     * Delete a translation from memory.
     *
     * @param  string  $source  Source text
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return bool True if deleted
     */
    public function delete(string $source, string $sourceLocale, string $targetLocale): bool
    {
        $entry = $this->repository->findExact($source, $sourceLocale, $targetLocale);

        if ($entry === null) {
            return false;
        }

        return $this->repository->delete($entry->getId());
    }

    /**
     * Check if a translation exists.
     *
     * @param  string  $source  Source text
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     */
    public function has(string $source, string $sourceLocale, string $targetLocale): bool
    {
        return $this->repository->findExact($source, $sourceLocale, $targetLocale) !== null;
    }

    /**
     * Search the translation memory.
     *
     * @param  string  $query  Search query
     * @param  string|null  $sourceLocale  Optional source locale filter
     * @param  string|null  $targetLocale  Optional target locale filter
     * @param  int  $limit  Maximum results
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function search(
        string $query,
        ?string $sourceLocale = null,
        ?string $targetLocale = null,
        int $limit = 50,
    ): Collection {
        return $this->repository->search($query, $sourceLocale, $targetLocale, $limit);
    }

    /**
     * Get all translations for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function getByLocalePair(string $sourceLocale, string $targetLocale): Collection
    {
        return $this->repository->findByLocalePair($sourceLocale, $targetLocale);
    }

    /**
     * Get translations needing review (low quality).
     *
     * @param  string|null  $sourceLocale  Optional source locale filter
     * @param  string|null  $targetLocale  Optional target locale filter
     * @return Collection<int, TranslationMemoryEntry>
     */
    public function getNeedsReview(?string $sourceLocale = null, ?string $targetLocale = null): Collection
    {
        $entries = $sourceLocale && $targetLocale
            ? $this->repository->findByLocalePair($sourceLocale, $targetLocale)
            : $this->repository->all();

        return $entries->filter(fn (TranslationMemoryEntry $e) => $e->needsReview());
    }

    /**
     * Import translations from a TMX file.
     *
     * @param  string  $filePath  Path to the TMX file
     * @param array{
     *     source_locale?: string,
     *     target_locale?: string,
     *     default_quality?: float,
     *     skip_existing?: bool,
     *     metadata?: array<string, mixed>,
     * } $options Import options
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     errors: array<string>,
     *     locales_found: array<string>,
     * }
     */
    public function importTmx(string $filePath, array $options = []): array
    {
        return $this->tmxImporter->importFromFile($filePath, $options);
    }

    /**
     * Import translations from TMX content.
     *
     * @param  string  $content  TMX content
     * @param  array  $options  Import options
     */
    public function importTmxContent(string $content, array $options = []): array
    {
        return $this->tmxImporter->importFromString($content, $options);
    }

    /**
     * Export translations to a TMX file.
     *
     * @param  string  $filePath  Output file path
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param array{
     *     include_metadata?: bool,
     *     min_quality?: float,
     *     creator?: string,
     * } $options Export options
     * @return array{
     *     success: bool,
     *     exported: int,
     *     file_size: int,
     *     error: string|null,
     * }
     */
    public function exportTmx(
        string $filePath,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): array {
        return $this->tmxExporter->exportToFile($filePath, $sourceLocale, $targetLocale, $options);
    }

    /**
     * Export all translations to a TMX file.
     *
     * @param  string  $filePath  Output file path
     * @param  array  $options  Export options
     */
    public function exportAllTmx(string $filePath, array $options = []): array
    {
        return $this->tmxExporter->exportAllToFile($filePath, $options);
    }

    /**
     * Get TMX content for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  array  $options  Export options
     * @return string TMX content
     */
    public function toTmx(string $sourceLocale, string $targetLocale, array $options = []): string
    {
        return $this->tmxExporter->exportToString($sourceLocale, $targetLocale, $options);
    }

    /**
     * Validate a TMX file.
     *
     * @param  string  $filePath  Path to the TMX file
     * @return array{
     *     valid: bool,
     *     version: string|null,
     *     entry_count: int,
     *     locales: array<string>,
     *     errors: array<string>,
     * }
     */
    public function validateTmx(string $filePath): array
    {
        return $this->tmxImporter->validate($filePath);
    }

    /**
     * Get translation memory statistics.
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
    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    /**
     * Get available locale pairs.
     *
     * @return array<array{source: string, target: string}>
     */
    public function getLocalePairs(): array
    {
        return $this->repository->getLocalePairs();
    }

    /**
     * Get the count of translations.
     *
     * @param  string|null  $sourceLocale  Optional source locale filter
     * @param  string|null  $targetLocale  Optional target locale filter
     */
    public function count(?string $sourceLocale = null, ?string $targetLocale = null): int
    {
        return $this->repository->count($sourceLocale, $targetLocale);
    }

    /**
     * Clear all translations for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return int Number of entries deleted
     */
    public function clearLocalePair(string $sourceLocale, string $targetLocale): int
    {
        return $this->repository->deleteByLocalePair($sourceLocale, $targetLocale);
    }

    /**
     * Clear all translations.
     *
     * @return int Number of entries deleted
     */
    public function clearAll(): int
    {
        return $this->repository->deleteAll();
    }

    /**
     * Get the underlying repository.
     */
    public function getRepository(): TranslationMemoryRepository
    {
        return $this->repository;
    }

    /**
     * Get the fuzzy matcher.
     */
    public function getFuzzyMatcher(): FuzzyMatcher
    {
        return $this->fuzzyMatcher;
    }

    /**
     * Calculate similarity between two strings.
     *
     * @param  string  $a  First string
     * @param  string  $b  Second string
     * @return float Similarity score (0.0-1.0)
     */
    public function calculateSimilarity(string $a, string $b): float
    {
        return $this->fuzzyMatcher->calculateSimilarity($a, $b);
    }
}
