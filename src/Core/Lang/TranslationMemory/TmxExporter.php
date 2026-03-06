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
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * TMX (Translation Memory eXchange) Exporter.
 *
 * Exports translation memory data to TMX format files.
 * TMX is the standard XML format for exchanging translation memories
 * between different tools and systems.
 *
 * Output conforms to TMX version 1.4b specification.
 *
 * Usage:
 *   $exporter = new TmxExporter($repository);
 *   $exporter->exportToFile('/path/to/memory.tmx', 'en_GB', 'de_DE');
 *   // Or export all locale pairs:
 *   $exporter->exportAllToFile('/path/to/memory.tmx');
 *
 * @see https://www.gala-global.org/tmx-14b
 */
class TmxExporter
{
    /**
     * TMX version to output.
     */
    protected const TMX_VERSION = '1.4';

    /**
     * Create a new TMX exporter.
     */
    public function __construct(
        protected TranslationMemoryRepository $repository,
    ) {}

    /**
     * Export translation memory to a TMX file.
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
    public function exportToFile(
        string $filePath,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): array {
        $entries = $this->repository->findByLocalePair($sourceLocale, $targetLocale);

        if (isset($options['min_quality'])) {
            $entries = $entries->filter(fn (TranslationMemoryEntry $e) => $e->getQuality() >= $options['min_quality']);
        }

        return $this->exportEntriesToFile($filePath, $entries, $sourceLocale, $options);
    }

    /**
     * Export all locale pairs to a single TMX file.
     *
     * @param  string  $filePath  Output file path
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
    public function exportAllToFile(string $filePath, array $options = []): array
    {
        $entries = $this->repository->all();

        if (isset($options['min_quality'])) {
            $entries = $entries->filter(fn (TranslationMemoryEntry $e) => $e->getQuality() >= $options['min_quality']);
        }

        // Determine primary source locale from entries
        $sourceLocale = $entries->first()?->getSourceLocale() ?? 'en';

        return $this->exportEntriesToFile($filePath, $entries, $sourceLocale, $options);
    }

    /**
     * Export to TMX string format.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param array{
     *     include_metadata?: bool,
     *     min_quality?: float,
     *     creator?: string,
     * } $options Export options
     * @return string TMX content
     */
    public function exportToString(
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): string {
        $entries = $this->repository->findByLocalePair($sourceLocale, $targetLocale);

        if (isset($options['min_quality'])) {
            $entries = $entries->filter(fn (TranslationMemoryEntry $e) => $e->getQuality() >= $options['min_quality']);
        }

        return $this->entriesToTmx($entries, $sourceLocale, $options);
    }

    /**
     * Export all entries to TMX string.
     *
     * @param array{
     *     include_metadata?: bool,
     *     min_quality?: float,
     *     creator?: string,
     * } $options Export options
     * @return string TMX content
     */
    public function exportAllToString(array $options = []): string
    {
        $entries = $this->repository->all();

        if (isset($options['min_quality'])) {
            $entries = $entries->filter(fn (TranslationMemoryEntry $e) => $e->getQuality() >= $options['min_quality']);
        }

        $sourceLocale = $entries->first()?->getSourceLocale() ?? 'en';

        return $this->entriesToTmx($entries, $sourceLocale, $options);
    }

    /**
     * Export entries to a TMX file.
     *
     * @param  string  $filePath  Output file path
     * @param  Collection<int, TranslationMemoryEntry>  $entries  Entries to export
     * @param  string  $sourceLocale  Primary source locale
     * @param  array  $options  Export options
     * @return array{
     *     success: bool,
     *     exported: int,
     *     file_size: int,
     *     error: string|null,
     * }
     */
    protected function exportEntriesToFile(string $filePath, Collection $entries, string $sourceLocale, array $options): array
    {
        $result = [
            'success' => false,
            'exported' => 0,
            'file_size' => 0,
            'error' => null,
        ];

        try {
            $tmx = $this->entriesToTmx($entries, $sourceLocale, $options);

            // Ensure directory exists
            $dir = dirname($filePath);
            if (! is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            File::put($filePath, $tmx);

            $result['success'] = true;
            $result['exported'] = $entries->count();
            $result['file_size'] = strlen($tmx);
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Convert entries to TMX XML string.
     *
     * @param  Collection<int, TranslationMemoryEntry>  $entries
     * @param  string  $sourceLocale  Primary source locale
     * @param  array  $options  Export options
     * @return string TMX XML content
     */
    protected function entriesToTmx(Collection $entries, string $sourceLocale, array $options): string
    {
        $includeMetadata = $options['include_metadata'] ?? true;
        $creator = $options['creator'] ?? 'Core PHP Translation Memory';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create TMX root element
        $tmx = $dom->createElement('tmx');
        $tmx->setAttribute('version', self::TMX_VERSION);
        $dom->appendChild($tmx);

        // Create header
        $header = $dom->createElement('header');
        $header->setAttribute('creationtool', 'Core PHP Framework');
        $header->setAttribute('creationtoolversion', '1.0');
        $header->setAttribute('segtype', 'sentence');
        $header->setAttribute('o-tmf', 'unknown');
        $header->setAttribute('adminlang', 'en');
        $header->setAttribute('srclang', $this->formatLocale($sourceLocale));
        $header->setAttribute('datatype', 'plaintext');
        $header->setAttribute('creationdate', (new DateTimeImmutable)->format('Ymd\THis\Z'));

        if (! empty($creator)) {
            $header->setAttribute('creationid', $creator);
        }

        $tmx->appendChild($header);

        // Create body
        $body = $dom->createElement('body');
        $tmx->appendChild($body);

        // Group entries by source text to create multi-language TUs
        $grouped = $this->groupEntriesBySource($entries);

        foreach ($grouped as $sourceText => $variants) {
            $tu = $this->createTu($dom, $sourceText, $variants, $includeMetadata);
            $body->appendChild($tu);
        }

        return $dom->saveXML();
    }

    /**
     * Group entries by source text for multi-language TUs.
     *
     * @param  Collection<int, TranslationMemoryEntry>  $entries
     * @return array<string, array<string, TranslationMemoryEntry>>
     */
    protected function groupEntriesBySource(Collection $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $sourceKey = $entry->getSourceLocale().':'.$entry->getSource();

            if (! isset($grouped[$sourceKey])) {
                $grouped[$sourceKey] = [
                    'source_locale' => $entry->getSourceLocale(),
                    'source_text' => $entry->getSource(),
                    'variants' => [],
                    'metadata' => $entry->getMetadata(),
                    'created_at' => $entry->getCreatedAt(),
                ];
            }

            // Add source variant if not already present
            if (! isset($grouped[$sourceKey]['variants'][$entry->getSourceLocale()])) {
                $grouped[$sourceKey]['variants'][$entry->getSourceLocale()] = $entry->getSource();
            }

            // Add target variant
            $grouped[$sourceKey]['variants'][$entry->getTargetLocale()] = $entry->getTarget();
        }

        return $grouped;
    }

    /**
     * Create a translation unit element.
     */
    protected function createTu(DOMDocument $dom, string $sourceKey, array $data, bool $includeMetadata): DOMElement
    {
        $tu = $dom->createElement('tu');

        // Add TU attributes
        $tu->setAttribute('tuid', hash('xxh64', $sourceKey));

        if (isset($data['created_at']) && $data['created_at'] instanceof DateTimeImmutable) {
            $tu->setAttribute('creationdate', $data['created_at']->format('Ymd\THis\Z'));
        }

        // Add metadata as prop elements
        if ($includeMetadata && ! empty($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $prop = $dom->createElement('prop', htmlspecialchars((string) $value));
                    $prop->setAttribute('type', $key);
                    $tu->appendChild($prop);
                }
            }
        }

        // Add translation unit variants
        foreach ($data['variants'] as $locale => $text) {
            $tuv = $dom->createElement('tuv');
            $tuv->setAttribute('xml:lang', $this->formatLocale($locale));

            $seg = $dom->createElement('seg');
            $seg->appendChild($dom->createTextNode($text));
            $tuv->appendChild($seg);

            $tu->appendChild($tuv);
        }

        return $tu;
    }

    /**
     * Format locale for TMX (using hyphen separator).
     */
    protected function formatLocale(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /**
     * Get export statistics for a locale pair.
     *
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @return array{
     *     total_entries: int,
     *     high_quality: int,
     *     needs_review: int,
     *     avg_quality: float,
     *     total_source_chars: int,
     *     total_target_chars: int,
     * }
     */
    public function getExportStats(string $sourceLocale, string $targetLocale): array
    {
        $entries = $this->repository->findByLocalePair($sourceLocale, $targetLocale);

        $totalQuality = 0;
        $highQuality = 0;
        $needsReview = 0;
        $sourceChars = 0;
        $targetChars = 0;

        foreach ($entries as $entry) {
            $totalQuality += $entry->getQuality();
            $sourceChars += mb_strlen($entry->getSource());
            $targetChars += mb_strlen($entry->getTarget());

            if ($entry->isHighQuality()) {
                $highQuality++;
            }
            if ($entry->needsReview()) {
                $needsReview++;
            }
        }

        $count = $entries->count();

        return [
            'total_entries' => $count,
            'high_quality' => $highQuality,
            'needs_review' => $needsReview,
            'avg_quality' => $count > 0 ? round($totalQuality / $count, 3) : 0.0,
            'total_source_chars' => $sourceChars,
            'total_target_chars' => $targetChars,
        ];
    }
}
