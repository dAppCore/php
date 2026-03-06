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
use DOMXPath;
use Illuminate\Support\Facades\File;

/**
 * TMX (Translation Memory eXchange) Importer.
 *
 * Imports translation memory data from TMX format files.
 * TMX is the standard XML format for exchanging translation memories
 * between different tools and systems.
 *
 * Supported TMX versions: 1.4
 *
 * Usage:
 *   $importer = new TmxImporter($repository);
 *   $result = $importer->importFromFile('/path/to/memory.tmx');
 *   echo "Imported {$result['imported']} entries";
 *
 * @see https://www.gala-global.org/tmx-14b
 */
class TmxImporter
{
    /**
     * Default quality score for imported translations.
     */
    protected const DEFAULT_QUALITY = 0.9;

    /**
     * Create a new TMX importer.
     */
    public function __construct(
        protected TranslationMemoryRepository $repository,
    ) {}

    /**
     * Import translation memory from a TMX file.
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
    public function importFromFile(string $filePath, array $options = []): array
    {
        if (! File::exists($filePath)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ["File not found: {$filePath}"],
                'locales_found' => [],
            ];
        }

        $content = File::get($filePath);

        return $this->importFromString($content, $options);
    }

    /**
     * Import translation memory from a TMX string.
     *
     * @param  string  $content  TMX content
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
    public function importFromString(string $content, array $options = []): array
    {
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'locales_found' => [],
        ];

        $defaultQuality = $options['default_quality'] ?? self::DEFAULT_QUALITY;
        $skipExisting = $options['skip_existing'] ?? true;
        $additionalMetadata = $options['metadata'] ?? [];
        $sourceLocaleFilter = $options['source_locale'] ?? null;
        $targetLocaleFilter = $options['target_locale'] ?? null;

        // Parse XML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;

        if (! $dom->loadXML($content)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $result['errors'][] = "XML Error: {$error->message} (line {$error->line})";
            }
            libxml_clear_errors();

            return $result;
        }

        $xpath = new DOMXPath($dom);

        // Get all translation units
        $tus = $xpath->query('//tu');

        if ($tus === false || $tus->length === 0) {
            $result['errors'][] = 'No translation units found in TMX file';

            return $result;
        }

        $entries = [];
        $localesFound = [];

        foreach ($tus as $tu) {
            if (! $tu instanceof DOMElement) {
                continue;
            }

            $tuResult = $this->parseTu($tu, $xpath, $sourceLocaleFilter, $targetLocaleFilter, $defaultQuality, $additionalMetadata);

            if ($tuResult === null) {
                continue;
            }

            foreach ($tuResult['locales'] as $locale) {
                $localesFound[$locale] = true;
            }

            foreach ($tuResult['entries'] as $entry) {
                // Check if entry already exists
                if ($skipExisting) {
                    $existing = $this->repository->findExact(
                        $entry->getSource(),
                        $entry->getSourceLocale(),
                        $entry->getTargetLocale()
                    );

                    if ($existing !== null) {
                        $result['skipped']++;

                        continue;
                    }
                }

                $entries[] = $entry;
            }
        }

        // Batch store entries
        if (! empty($entries)) {
            $result['imported'] = $this->repository->storeBatch($entries);
        }

        $result['locales_found'] = array_keys($localesFound);

        return $result;
    }

    /**
     * Parse a translation unit (tu) element.
     *
     * @param  DOMElement  $tu  The tu element
     * @param  DOMXPath  $xpath  XPath instance
     * @param  string|null  $sourceLocaleFilter  Optional source locale filter
     * @param  string|null  $targetLocaleFilter  Optional target locale filter
     * @param  float  $defaultQuality  Default quality score
     * @param  array<string, mixed>  $additionalMetadata  Additional metadata
     * @return array{entries: array<TranslationMemoryEntry>, locales: array<string>}|null
     */
    protected function parseTu(
        DOMElement $tu,
        DOMXPath $xpath,
        ?string $sourceLocaleFilter,
        ?string $targetLocaleFilter,
        float $defaultQuality,
        array $additionalMetadata,
    ): ?array {
        // Get all tuv (translation unit variant) elements
        $tuvs = $xpath->query('tuv', $tu);

        if ($tuvs === false || $tuvs->length < 2) {
            return null;
        }

        // Extract TU-level metadata
        $tuMetadata = $this->extractMetadata($tu);

        // Parse all variants
        $variants = [];
        $localesFound = [];

        foreach ($tuvs as $tuv) {
            if (! $tuv instanceof DOMElement) {
                continue;
            }

            $locale = $tuv->getAttribute('xml:lang') ?: $tuv->getAttribute('lang');

            if (empty($locale)) {
                continue;
            }

            // Normalize locale format
            $locale = $this->normalizeLocale($locale);
            $localesFound[] = $locale;

            // Get the segment content
            $seg = $xpath->query('seg', $tuv)->item(0);

            if (! $seg instanceof DOMElement) {
                continue;
            }

            $text = $this->extractSegmentText($seg);

            if (empty($text)) {
                continue;
            }

            // Extract TUV-level metadata
            $tuvMetadata = $this->extractMetadata($tuv);

            $variants[$locale] = [
                'text' => $text,
                'metadata' => array_merge($tuMetadata, $tuvMetadata),
            ];
        }

        if (count($variants) < 2) {
            return null;
        }

        // Create entries for each locale pair
        $entries = [];
        $localesList = array_keys($variants);

        foreach ($localesList as $sourceLocale) {
            foreach ($localesList as $targetLocale) {
                if ($sourceLocale === $targetLocale) {
                    continue;
                }

                // Apply locale filters
                if ($sourceLocaleFilter !== null && $sourceLocale !== $sourceLocaleFilter) {
                    continue;
                }
                if ($targetLocaleFilter !== null && $targetLocale !== $targetLocaleFilter) {
                    continue;
                }

                $sourceVariant = $variants[$sourceLocale];
                $targetVariant = $variants[$targetLocale];

                $metadata = array_merge(
                    $sourceVariant['metadata'],
                    $additionalMetadata,
                    ['imported_from' => 'tmx'],
                );

                // Extract creation date if available
                $createdAt = null;
                if (isset($metadata['creationdate'])) {
                    try {
                        $createdAt = new DateTimeImmutable($metadata['creationdate']);
                    } catch (\Exception $e) {
                        // Invalid date format, ignore
                    }
                }

                $entries[] = new TranslationMemoryEntry(
                    id: TranslationMemoryEntry::generateId($sourceVariant['text'], $sourceLocale, $targetLocale),
                    sourceLocale: $sourceLocale,
                    targetLocale: $targetLocale,
                    source: $sourceVariant['text'],
                    target: $targetVariant['text'],
                    quality: $defaultQuality,
                    createdAt: $createdAt,
                    metadata: $metadata,
                );
            }
        }

        return [
            'entries' => $entries,
            'locales' => array_unique($localesFound),
        ];
    }

    /**
     * Extract metadata from a TMX element.
     *
     * @return array<string, mixed>
     */
    protected function extractMetadata(DOMElement $element): array
    {
        $metadata = [];

        // Standard TMX attributes
        $attrs = ['tuid', 'srclang', 'datatype', 'usagecount', 'creationdate', 'changedate', 'changeid', 'creationid'];

        foreach ($attrs as $attr) {
            $value = $element->getAttribute($attr);
            if (! empty($value)) {
                $metadata[$attr] = $value;
            }
        }

        // Get prop elements
        foreach ($element->getElementsByTagName('prop') as $prop) {
            if (! $prop instanceof DOMElement) {
                continue;
            }

            $type = $prop->getAttribute('type');
            if (! empty($type)) {
                $metadata['prop_'.$type] = $prop->textContent;
            }
        }

        // Get note elements
        $notes = [];
        foreach ($element->getElementsByTagName('note') as $note) {
            if (! $note instanceof DOMElement) {
                continue;
            }
            $notes[] = $note->textContent;
        }
        if (! empty($notes)) {
            $metadata['notes'] = $notes;
        }

        return $metadata;
    }

    /**
     * Extract text content from a segment element.
     *
     * Handles inline elements like <bpt>, <ept>, <it>, <ph>, <hi>.
     */
    protected function extractSegmentText(DOMElement $seg): string
    {
        // Get text content, preserving inline formatting markers
        $text = '';

        foreach ($seg->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE && $child instanceof DOMElement) {
                // Handle inline elements
                $tagName = $child->tagName;

                switch ($tagName) {
                    case 'bpt': // Beginning paired tag
                    case 'ept': // Ending paired tag
                    case 'it':  // Isolated tag
                    case 'ph':  // Placeholder
                        // Include placeholder content or use marker
                        $text .= $child->textContent ?: '{'.$tagName.'}';

                        break;
                    case 'hi':  // Highlight
                        // Include highlighted content as-is
                        $text .= $child->textContent;

                        break;
                    default:
                        // Include any other element's text content
                        $text .= $child->textContent;
                }
            }
        }

        return trim($text);
    }

    /**
     * Normalize locale format.
     *
     * Converts various locale formats to consistent underscore format.
     * e.g., 'en-GB' -> 'en_GB', 'EN_gb' -> 'en_GB'
     */
    protected function normalizeLocale(string $locale): string
    {
        // Replace hyphen with underscore
        $locale = str_replace('-', '_', $locale);

        // Split into parts
        $parts = explode('_', $locale, 2);

        // Language code lowercase, country code uppercase
        $language = strtolower($parts[0]);

        if (isset($parts[1])) {
            return $language.'_'.strtoupper($parts[1]);
        }

        return $language;
    }

    /**
     * Validate a TMX file without importing.
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
    public function validate(string $filePath): array
    {
        $result = [
            'valid' => false,
            'version' => null,
            'entry_count' => 0,
            'locales' => [],
            'errors' => [],
        ];

        if (! File::exists($filePath)) {
            $result['errors'][] = "File not found: {$filePath}";

            return $result;
        }

        $content = File::get($filePath);

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;

        if (! $dom->loadXML($content)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $result['errors'][] = "XML Error: {$error->message} (line {$error->line})";
            }
            libxml_clear_errors();

            return $result;
        }

        $xpath = new DOMXPath($dom);

        // Check for TMX root element
        $tmx = $xpath->query('/tmx')->item(0);
        if (! $tmx instanceof DOMElement) {
            $result['errors'][] = 'Not a valid TMX file: missing <tmx> root element';

            return $result;
        }

        // Get version
        $result['version'] = $tmx->getAttribute('version') ?: null;

        // Count translation units
        $tus = $xpath->query('//tu');
        $result['entry_count'] = $tus !== false ? $tus->length : 0;

        // Collect locales
        $locales = [];
        $tuvs = $xpath->query('//tuv');

        if ($tuvs !== false) {
            foreach ($tuvs as $tuv) {
                if (! $tuv instanceof DOMElement) {
                    continue;
                }

                $locale = $tuv->getAttribute('xml:lang') ?: $tuv->getAttribute('lang');
                if (! empty($locale)) {
                    $locales[$this->normalizeLocale($locale)] = true;
                }
            }
        }

        $result['locales'] = array_keys($locales);
        $result['valid'] = true;

        return $result;
    }
}
