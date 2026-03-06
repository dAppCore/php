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
use Illuminate\Support\Facades\File;

/**
 * JSON File-based Translation Memory Repository.
 *
 * Stores translation memory entries in JSON files organized by locale pairs.
 * Suitable for small to medium translation memories with fast reads.
 *
 * File structure:
 *   {storage_path}/
 *     en_GB-de_DE.json
 *     en_GB-fr_FR.json
 *     ...
 *
 * Each file contains an array of TranslationMemoryEntry objects.
 *
 * For larger translation memories, consider using a database-backed
 * implementation instead.
 */
class JsonTranslationMemoryRepository implements TranslationMemoryRepository
{
    /**
     * In-memory cache of entries by locale pair.
     *
     * @var array<string, Collection<int, TranslationMemoryEntry>>
     */
    protected array $cache = [];

    /**
     * Dirty flags for modified locale pairs.
     *
     * @var array<string, bool>
     */
    protected array $dirty = [];

    /**
     * Create a new JSON repository.
     *
     * @param  string  $storagePath  Path to store translation memory files
     */
    public function __construct(
        protected string $storagePath,
    ) {
        // Ensure storage directory exists
        if (! is_dir($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function store(TranslationMemoryEntry $entry): bool
    {
        $localePair = $this->getLocalePairKey($entry->getSourceLocale(), $entry->getTargetLocale());
        $entries = $this->loadLocalePair($localePair);

        // Check for existing entry with same ID
        $existingIndex = $entries->search(fn (TranslationMemoryEntry $e) => $e->getId() === $entry->getId());

        if ($existingIndex !== false) {
            $entries[$existingIndex] = $entry;
        } else {
            $entries->push($entry);
        }

        $this->cache[$localePair] = $entries;
        $this->dirty[$localePair] = true;

        return $this->persist($localePair);
    }

    /**
     * {@inheritdoc}
     */
    public function storeBatch(iterable $entries): int
    {
        $count = 0;
        $localePairUpdates = [];

        foreach ($entries as $entry) {
            $localePair = $this->getLocalePairKey($entry->getSourceLocale(), $entry->getTargetLocale());

            if (! isset($localePairUpdates[$localePair])) {
                $localePairUpdates[$localePair] = $this->loadLocalePair($localePair);
            }

            // Check for existing entry with same ID
            $existingIndex = $localePairUpdates[$localePair]->search(
                fn (TranslationMemoryEntry $e) => $e->getId() === $entry->getId()
            );

            if ($existingIndex !== false) {
                $localePairUpdates[$localePair][$existingIndex] = $entry;
            } else {
                $localePairUpdates[$localePair]->push($entry);
            }

            $count++;
        }

        // Persist all modified locale pairs
        foreach ($localePairUpdates as $localePair => $entries) {
            $this->cache[$localePair] = $entries;
            $this->dirty[$localePair] = true;
            $this->persist($localePair);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function findExact(string $source, string $sourceLocale, string $targetLocale): ?TranslationMemoryEntry
    {
        $localePair = $this->getLocalePairKey($sourceLocale, $targetLocale);
        $entries = $this->loadLocalePair($localePair);

        return $entries->first(fn (TranslationMemoryEntry $entry) => $entry->getSource() === $source);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $id): ?TranslationMemoryEntry
    {
        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);
            $entry = $entries->first(fn (TranslationMemoryEntry $e) => $e->getId() === $id);

            if ($entry !== null) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByLocalePair(string $sourceLocale, string $targetLocale): Collection
    {
        $localePair = $this->getLocalePairKey($sourceLocale, $targetLocale);

        return $this->loadLocalePair($localePair);
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, ?string $sourceLocale = null, ?string $targetLocale = null, int $limit = 50): Collection
    {
        $query = mb_strtolower($query);
        $results = collect();

        $localePairs = $sourceLocale && $targetLocale
            ? [$this->getLocalePairKey($sourceLocale, $targetLocale)]
            : $this->getAllLocalePairs();

        foreach ($localePairs as $localePair) {
            $entries = $this->loadLocalePair($localePair);

            $matches = $entries->filter(function (TranslationMemoryEntry $entry) use ($query, $sourceLocale, $targetLocale) {
                // Apply locale filters if specified individually
                if ($sourceLocale && $entry->getSourceLocale() !== $sourceLocale) {
                    return false;
                }
                if ($targetLocale && $entry->getTargetLocale() !== $targetLocale) {
                    return false;
                }

                // Search in both source and target text
                return str_contains(mb_strtolower($entry->getSource()), $query)
                    || str_contains(mb_strtolower($entry->getTarget()), $query);
            });

            $results = $results->merge($matches);

            if ($results->count() >= $limit) {
                break;
            }
        }

        return $results->take($limit)->values();
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        $all = collect();

        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);
            $all = $all->merge($entries);
        }

        return $all;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $id): bool
    {
        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);
            $initialCount = $entries->count();

            $entries = $entries->reject(fn (TranslationMemoryEntry $e) => $e->getId() === $id);

            if ($entries->count() < $initialCount) {
                $this->cache[$localePair] = $entries->values();
                $this->dirty[$localePair] = true;
                $this->persist($localePair);

                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByLocalePair(string $sourceLocale, string $targetLocale): int
    {
        $localePair = $this->getLocalePairKey($sourceLocale, $targetLocale);
        $entries = $this->loadLocalePair($localePair);
        $count = $entries->count();

        if ($count > 0) {
            $this->cache[$localePair] = collect();
            $this->dirty[$localePair] = true;
            $this->persist($localePair);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(): int
    {
        $count = 0;

        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);
            $count += $entries->count();

            $filePath = $this->getFilePath($localePair);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        $this->cache = [];
        $this->dirty = [];

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function count(?string $sourceLocale = null, ?string $targetLocale = null): int
    {
        if ($sourceLocale && $targetLocale) {
            return $this->findByLocalePair($sourceLocale, $targetLocale)->count();
        }

        $count = 0;

        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);

            if ($sourceLocale || $targetLocale) {
                $entries = $entries->filter(function (TranslationMemoryEntry $entry) use ($sourceLocale, $targetLocale) {
                    if ($sourceLocale && $entry->getSourceLocale() !== $sourceLocale) {
                        return false;
                    }
                    if ($targetLocale && $entry->getTargetLocale() !== $targetLocale) {
                        return false;
                    }

                    return true;
                });
            }

            $count += $entries->count();
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalePairs(): array
    {
        $pairs = [];

        foreach ($this->getAllLocalePairs() as $localePair) {
            [$source, $target] = explode('-', $localePair, 2);
            $pairs[] = ['source' => $source, 'target' => $target];
        }

        return $pairs;
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        $totalEntries = 0;
        $totalQuality = 0;
        $highQualityCount = 0;
        $needsReviewCount = 0;
        $totalUsage = 0;

        foreach ($this->getAllLocalePairs() as $localePair) {
            $entries = $this->loadLocalePair($localePair);

            foreach ($entries as $entry) {
                $totalEntries++;
                $totalQuality += $entry->getQuality();
                $totalUsage += $entry->getUsageCount();

                if ($entry->isHighQuality()) {
                    $highQualityCount++;
                }
                if ($entry->needsReview()) {
                    $needsReviewCount++;
                }
            }
        }

        return [
            'total_entries' => $totalEntries,
            'locale_pairs' => count($this->getAllLocalePairs()),
            'avg_quality' => $totalEntries > 0 ? round($totalQuality / $totalEntries, 3) : 0.0,
            'high_quality_count' => $highQualityCount,
            'needs_review_count' => $needsReviewCount,
            'total_usage' => $totalUsage,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function incrementUsage(string $id): bool
    {
        $entry = $this->findById($id);

        if ($entry === null) {
            return false;
        }

        return $this->store($entry->withIncrementedUsage());
    }

    /**
     * {@inheritdoc}
     */
    public function updateQuality(string $id, float $quality): bool
    {
        $entry = $this->findById($id);

        if ($entry === null) {
            return false;
        }

        return $this->store($entry->withQuality($quality));
    }

    /**
     * Load entries for a locale pair.
     *
     * @return Collection<int, TranslationMemoryEntry>
     */
    protected function loadLocalePair(string $localePair): Collection
    {
        if (isset($this->cache[$localePair])) {
            return $this->cache[$localePair];
        }

        $filePath = $this->getFilePath($localePair);

        if (! File::exists($filePath)) {
            $this->cache[$localePair] = collect();

            return $this->cache[$localePair];
        }

        try {
            $data = json_decode(File::get($filePath), true);

            if (! is_array($data)) {
                $this->cache[$localePair] = collect();

                return $this->cache[$localePair];
            }

            $entries = collect($data)->map(fn (array $item) => TranslationMemoryEntry::fromArray($item));

            $this->cache[$localePair] = $entries;

            return $entries;
        } catch (\Exception $e) {
            $this->cache[$localePair] = collect();

            return $this->cache[$localePair];
        }
    }

    /**
     * Persist entries for a locale pair to disk.
     */
    protected function persist(string $localePair): bool
    {
        if (! isset($this->dirty[$localePair]) || ! $this->dirty[$localePair]) {
            return true;
        }

        $entries = $this->cache[$localePair] ?? collect();
        $filePath = $this->getFilePath($localePair);

        try {
            $data = $entries->map(fn (TranslationMemoryEntry $entry) => $entry->toArray())->all();
            File::put($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->dirty[$localePair] = false;

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all locale pair keys from files.
     *
     * @return array<string>
     */
    protected function getAllLocalePairs(): array
    {
        $pairs = [];

        if (! is_dir($this->storagePath)) {
            return $pairs;
        }

        foreach (File::files($this->storagePath) as $file) {
            if ($file->getExtension() === 'json') {
                $pairs[] = $file->getFilenameWithoutExtension();
            }
        }

        // Also include any cached locale pairs that haven't been persisted yet
        foreach (array_keys($this->cache) as $localePair) {
            if (! in_array($localePair, $pairs, true)) {
                $pairs[] = $localePair;
            }
        }

        return $pairs;
    }

    /**
     * Get the locale pair key.
     */
    protected function getLocalePairKey(string $sourceLocale, string $targetLocale): string
    {
        return "{$sourceLocale}-{$targetLocale}";
    }

    /**
     * Get the file path for a locale pair.
     */
    protected function getFilePath(string $localePair): string
    {
        return $this->storagePath.'/'.$localePair.'.json';
    }

    /**
     * Flush all changes to disk.
     */
    public function flush(): void
    {
        foreach (array_keys($this->dirty) as $localePair) {
            $this->persist($localePair);
        }
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
