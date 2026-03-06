<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Search\Support;

/**
 * Search result highlighter for matched terms.
 *
 * Provides utilities to highlight search query terms within result text,
 * making it easier for users to see why a result matched their query.
 *
 * ## Features
 *
 * - Highlights exact matches with configurable wrapper tags
 * - Supports multi-word queries (highlights each word)
 * - Case-insensitive matching with original case preserved
 * - HTML-safe output with proper escaping
 * - Context extraction (snippets around matches)
 * - Configurable highlight styles
 *
 * ## Usage
 *
 * ```php
 * $highlighter = new SearchHighlighter();
 *
 * // Simple highlighting
 * $highlighted = $highlighter->highlight('The quick brown fox', 'quick');
 * // Returns: 'The <mark class="search-highlight">quick</mark> brown fox'
 *
 * // Multi-word query
 * $highlighted = $highlighter->highlight('The quick brown fox', 'quick fox');
 * // Returns: 'The <mark class="search-highlight">quick</mark> brown <mark class="search-highlight">fox</mark>'
 *
 * // Extract snippet with context
 * $snippet = $highlighter->snippet($longText, 'search term', 50);
 * // Returns: '...text around <mark>search</mark> <mark>term</mark> with context...'
 * ```
 */
class SearchHighlighter
{
    /**
     * Default CSS class for highlight wrapper.
     */
    protected const DEFAULT_CLASS = 'search-highlight';

    /**
     * Default number of context characters for snippets.
     */
    protected const DEFAULT_CONTEXT_LENGTH = 50;

    /**
     * Minimum word length to highlight.
     */
    protected const MIN_WORD_LENGTH = 2;

    /**
     * The wrapper tag to use for highlighting.
     */
    protected string $tag = 'mark';

    /**
     * CSS class(es) to apply to the highlight wrapper.
     */
    protected string $class = self::DEFAULT_CLASS;

    /**
     * Whether to escape HTML in the input text.
     */
    protected bool $escapeHtml = true;

    /**
     * Minimum word length to highlight.
     */
    protected int $minWordLength = self::MIN_WORD_LENGTH;

    /**
     * Create a new highlighter instance.
     */
    public function __construct(
        ?string $tag = null,
        ?string $class = null,
        bool $escapeHtml = true
    ) {
        if ($tag !== null) {
            $this->tag = $tag;
        }
        if ($class !== null) {
            $this->class = $class;
        }
        $this->escapeHtml = $escapeHtml;
    }

    /**
     * Set the wrapper tag for highlighting.
     *
     * @param  string  $tag  HTML tag name (e.g., 'mark', 'span', 'strong')
     */
    public function tag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Set the CSS class for the highlight wrapper.
     *
     * @param  string  $class  CSS class name(s)
     */
    public function class(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Set whether to escape HTML in the input text.
     *
     * @param  bool  $escape  Whether to escape HTML entities
     */
    public function escapeHtml(bool $escape): static
    {
        $this->escapeHtml = $escape;

        return $this;
    }

    /**
     * Set the minimum word length to highlight.
     *
     * @param  int  $length  Minimum characters for a word to be highlighted
     */
    public function minWordLength(int $length): static
    {
        $this->minWordLength = max(1, $length);

        return $this;
    }

    /**
     * Highlight search terms in text.
     *
     * @param  string  $text  The text to highlight within
     * @param  string  $query  The search query (space-separated terms)
     * @return string The text with highlighted terms
     */
    public function highlight(string $text, string $query): string
    {
        if (empty($text) || empty($query)) {
            return $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        }

        // Extract words from the query
        $words = $this->extractWords($query);

        if (empty($words)) {
            return $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        }

        // Build a regex pattern that matches any of the words
        $pattern = $this->buildPattern($words);

        // If escaping HTML, we need to be careful about the order
        if ($this->escapeHtml) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Replace matches with highlighted version
        return preg_replace_callback(
            $pattern,
            fn (array $matches) => $this->wrapMatch($matches[0]),
            $text
        ) ?? $text;
    }

    /**
     * Highlight and extract a snippet around the first match.
     *
     * @param  string  $text  The text to search within
     * @param  string  $query  The search query
     * @param  int  $contextLength  Number of characters before/after match
     * @return string The snippet with highlighted terms
     */
    public function snippet(string $text, string $query, int $contextLength = self::DEFAULT_CONTEXT_LENGTH): string
    {
        if (empty($text) || empty($query)) {
            return $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        }

        $words = $this->extractWords($query);

        if (empty($words)) {
            return $this->truncate($text, $contextLength * 2);
        }

        // Find the first match position
        $firstMatchPos = $this->findFirstMatch($text, $words);

        if ($firstMatchPos === null) {
            return $this->truncate($text, $contextLength * 2);
        }

        // Extract snippet around the match
        $snippet = $this->extractContext($text, $firstMatchPos, $contextLength);

        // Highlight terms in the snippet
        return $this->highlight($snippet, $query);
    }

    /**
     * Highlight all matches and return structured data.
     *
     * Useful for programmatic access to match positions and counts.
     *
     * @param  string  $text  The text to search within
     * @param  string  $query  The search query
     * @return array{text: string, matches: array<int, array{word: string, position: int, length: int}>, count: int}
     */
    public function highlightWithMeta(string $text, string $query): array
    {
        $matches = [];
        $count = 0;

        if (empty($text) || empty($query)) {
            return [
                'text' => $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text,
                'matches' => $matches,
                'count' => $count,
            ];
        }

        $words = $this->extractWords($query);

        if (empty($words)) {
            return [
                'text' => $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text,
                'matches' => $matches,
                'count' => $count,
            ];
        }

        $pattern = $this->buildPattern($words);
        $lowerText = mb_strtolower($text);

        // Find all match positions
        foreach ($words as $word) {
            $pos = 0;
            while (($pos = mb_strpos($lowerText, $word, $pos)) !== false) {
                $matches[] = [
                    'word' => $word,
                    'position' => $pos,
                    'length' => mb_strlen($word),
                ];
                $count++;
                $pos += mb_strlen($word);
            }
        }

        // Sort matches by position
        usort($matches, fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return [
            'text' => $this->highlight($text, $query),
            'matches' => $matches,
            'count' => $count,
        ];
    }

    /**
     * Extract searchable words from a query string.
     *
     * @param  string  $query  The search query
     * @return array<int, string> Array of words to highlight
     */
    protected function extractWords(string $query): array
    {
        $query = trim($query);

        if (empty($query)) {
            return [];
        }

        // Split on whitespace and filter short words
        $words = preg_split('/\s+/', mb_strtolower($query));

        return array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= $this->minWordLength
        ));
    }

    /**
     * Build a regex pattern for matching words.
     *
     * @param  array<int, string>  $words  Words to match
     * @return string Regex pattern
     */
    protected function buildPattern(array $words): string
    {
        // Escape regex special characters and join with alternation
        $escaped = array_map(fn (string $word) => preg_quote($word, '/'), $words);

        // Use word boundary where possible, case-insensitive
        return '/('.implode('|', $escaped).')/iu';
    }

    /**
     * Wrap a match in the highlight tag.
     *
     * @param  string  $match  The matched text
     * @return string The wrapped text
     */
    protected function wrapMatch(string $match): string
    {
        $classAttr = $this->class ? ' class="'.htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8').'"' : '';

        return "<{$this->tag}{$classAttr}>{$match}</{$this->tag}>";
    }

    /**
     * Find the position of the first match in text.
     *
     * @param  string  $text  Text to search
     * @param  array<int, string>  $words  Words to find
     * @return int|null Position of first match or null
     */
    protected function findFirstMatch(string $text, array $words): ?int
    {
        $lowerText = mb_strtolower($text);
        $firstPos = null;

        foreach ($words as $word) {
            $pos = mb_strpos($lowerText, $word);
            if ($pos !== false && ($firstPos === null || $pos < $firstPos)) {
                $firstPos = $pos;
            }
        }

        return $firstPos;
    }

    /**
     * Extract context around a position in text.
     *
     * @param  string  $text  The full text
     * @param  int  $position  Center position
     * @param  int  $contextLength  Characters before/after
     * @return string Extracted context with ellipsis if truncated
     */
    protected function extractContext(string $text, int $position, int $contextLength): string
    {
        $textLength = mb_strlen($text);

        // Calculate start and end positions
        $start = max(0, $position - $contextLength);
        $end = min($textLength, $position + $contextLength);

        // Try to start at word boundary
        if ($start > 0) {
            $wordStart = mb_strrpos(mb_substr($text, 0, $start + 10), ' ');
            if ($wordStart !== false && $wordStart >= $start - 10) {
                $start = $wordStart + 1;
            }
        }

        // Try to end at word boundary
        if ($end < $textLength) {
            $wordEnd = mb_strpos($text, ' ', $end);
            if ($wordEnd !== false && $wordEnd <= $end + 10) {
                $end = $wordEnd;
            }
        }

        // Extract the context
        $context = mb_substr($text, $start, $end - $start);

        // Add ellipsis where truncated
        $prefix = $start > 0 ? '...' : '';
        $suffix = $end < $textLength ? '...' : '';

        return $prefix.trim($context).$suffix;
    }

    /**
     * Truncate text to a maximum length.
     *
     * @param  string  $text  Text to truncate
     * @param  int  $length  Maximum length
     * @return string Truncated text
     */
    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $this->escapeHtml ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        }

        $truncated = mb_substr($text, 0, $length);

        // Try to end at word boundary
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $length * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        $result = trim($truncated).'...';

        return $this->escapeHtml ? htmlspecialchars($result, ENT_QUOTES, 'UTF-8') : $result;
    }

    /**
     * Create a new highlighter with default configuration.
     */
    public static function make(): static
    {
        return new static;
    }
}
