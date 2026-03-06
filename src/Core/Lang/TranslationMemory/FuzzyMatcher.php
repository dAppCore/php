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
 * Fuzzy Matcher for Translation Memory.
 *
 * Provides fuzzy matching capabilities to find similar translations
 * when an exact match is not available. Uses multiple algorithms:
 *
 * - Levenshtein distance for character-level similarity
 * - Token/word-based matching for structural similarity
 * - N-gram matching for partial phrase matching
 *
 * Match scores are combined with the translation quality score
 * to provide a confidence rating for each suggestion.
 *
 * Usage:
 *   $matcher = new FuzzyMatcher($repository);
 *   $suggestions = $matcher->findSimilar('Hello world', 'en_GB', 'de_DE', 0.7);
 *
 * Configuration in config/core.php:
 *   'lang' => [
 *       'translation_memory' => [
 *           'fuzzy' => [
 *               'min_similarity' => 0.6,
 *               'max_results' => 10,
 *               'algorithm' => 'combined', // levenshtein, token, ngram, combined
 *           ],
 *       ],
 *   ]
 */
class FuzzyMatcher
{
    /**
     * Default minimum similarity threshold.
     */
    protected const DEFAULT_MIN_SIMILARITY = 0.6;

    /**
     * Default maximum results to return.
     */
    protected const DEFAULT_MAX_RESULTS = 10;

    /**
     * N-gram size for n-gram matching.
     */
    protected const NGRAM_SIZE = 3;

    /**
     * Create a new fuzzy matcher.
     */
    public function __construct(
        protected TranslationMemoryRepository $repository,
    ) {}

    /**
     * Find similar translations using fuzzy matching.
     *
     * @param  string  $source  Source text to match
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity threshold (0.0-1.0)
     * @param  int|null  $maxResults  Maximum number of results
     * @return Collection<int, array{entry: TranslationMemoryEntry, similarity: float, confidence: float}>
     */
    public function findSimilar(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
        ?int $maxResults = null,
    ): Collection {
        $minSimilarity ??= config('core.lang.translation_memory.fuzzy.min_similarity', self::DEFAULT_MIN_SIMILARITY);
        $maxResults ??= config('core.lang.translation_memory.fuzzy.max_results', self::DEFAULT_MAX_RESULTS);

        $entries = $this->repository->findByLocalePair($sourceLocale, $targetLocale);

        if ($entries->isEmpty()) {
            return collect();
        }

        $normalizedSource = $this->normalize($source);

        return $entries
            ->map(function (TranslationMemoryEntry $entry) use ($normalizedSource) {
                $similarity = $this->calculateSimilarity($normalizedSource, $this->normalize($entry->getSource()));

                // Combine similarity with quality score for overall confidence
                $confidence = ($similarity * 0.7) + ($entry->getQuality() * 0.3);

                return [
                    'entry' => $entry,
                    'similarity' => round($similarity, 4),
                    'confidence' => round($confidence, 4),
                ];
            })
            ->filter(fn (array $match) => $match['similarity'] >= $minSimilarity)
            ->sortByDesc('confidence')
            ->take($maxResults)
            ->values();
    }

    /**
     * Get the best match for a source text.
     *
     * @param  string  $source  Source text to match
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity threshold
     * @return array{entry: TranslationMemoryEntry, similarity: float, confidence: float}|null
     */
    public function getBestMatch(
        string $source,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
    ): ?array {
        // First, try exact match
        $exact = $this->repository->findExact($source, $sourceLocale, $targetLocale);

        if ($exact !== null) {
            return [
                'entry' => $exact,
                'similarity' => 1.0,
                'confidence' => $exact->getQuality(),
            ];
        }

        // Fall back to fuzzy match
        $matches = $this->findSimilar($source, $sourceLocale, $targetLocale, $minSimilarity, 1);

        return $matches->first();
    }

    /**
     * Calculate similarity between two strings using combined algorithms.
     *
     * @return float Similarity score (0.0-1.0)
     */
    public function calculateSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $algorithm = config('core.lang.translation_memory.fuzzy.algorithm', 'combined');

        return match ($algorithm) {
            'levenshtein' => $this->levenshteinSimilarity($a, $b),
            'token' => $this->tokenSimilarity($a, $b),
            'ngram' => $this->ngramSimilarity($a, $b),
            default => $this->combinedSimilarity($a, $b),
        };
    }

    /**
     * Calculate Levenshtein-based similarity.
     *
     * @return float Similarity score (0.0-1.0)
     */
    protected function levenshteinSimilarity(string $a, string $b): float
    {
        $maxLen = max(mb_strlen($a), mb_strlen($b));

        if ($maxLen === 0) {
            return 1.0;
        }

        // Use built-in levenshtein for short strings, approximate for long
        if ($maxLen <= 255) {
            $distance = levenshtein($a, $b);
        } else {
            // For longer strings, use word-level Levenshtein
            $wordsA = explode(' ', $a);
            $wordsB = explode(' ', $b);
            $distance = $this->arrayLevenshtein($wordsA, $wordsB);
            $maxLen = max(count($wordsA), count($wordsB));
        }

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Calculate token/word-based similarity.
     *
     * Uses Jaccard similarity on word tokens.
     *
     * @return float Similarity score (0.0-1.0)
     */
    protected function tokenSimilarity(string $a, string $b): float
    {
        $tokensA = $this->tokenize($a);
        $tokensB = $this->tokenize($b);

        if (empty($tokensA) && empty($tokensB)) {
            return 1.0;
        }

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        return count($intersection) / count($union);
    }

    /**
     * Calculate n-gram similarity.
     *
     * Uses character n-grams for partial matching.
     *
     * @return float Similarity score (0.0-1.0)
     */
    protected function ngramSimilarity(string $a, string $b): float
    {
        $ngramsA = $this->getNgrams($a);
        $ngramsB = $this->getNgrams($b);

        if (empty($ngramsA) && empty($ngramsB)) {
            return 1.0;
        }

        if (empty($ngramsA) || empty($ngramsB)) {
            return 0.0;
        }

        $intersection = array_intersect_key($ngramsA, $ngramsB);
        $intersectionCount = 0;

        foreach ($intersection as $ngram => $_) {
            $intersectionCount += min($ngramsA[$ngram], $ngramsB[$ngram]);
        }

        $totalA = array_sum($ngramsA);
        $totalB = array_sum($ngramsB);

        // Dice coefficient
        return (2 * $intersectionCount) / ($totalA + $totalB);
    }

    /**
     * Calculate combined similarity using multiple algorithms.
     *
     * Weighted combination of Levenshtein, token, and n-gram similarity.
     *
     * @return float Similarity score (0.0-1.0)
     */
    protected function combinedSimilarity(string $a, string $b): float
    {
        $levenshtein = $this->levenshteinSimilarity($a, $b);
        $token = $this->tokenSimilarity($a, $b);
        $ngram = $this->ngramSimilarity($a, $b);

        // Weighted average: token similarity is most important for translations
        return ($levenshtein * 0.25) + ($token * 0.50) + ($ngram * 0.25);
    }

    /**
     * Normalize text for comparison.
     */
    protected function normalize(string $text): string
    {
        // Lowercase
        $text = mb_strtolower($text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove leading/trailing whitespace
        $text = trim($text);

        return $text;
    }

    /**
     * Tokenize text into words.
     *
     * @return array<string>
     */
    protected function tokenize(string $text): array
    {
        // Split on word boundaries
        $tokens = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ?: [];
    }

    /**
     * Get character n-grams from text.
     *
     * @return array<string, int> N-gram counts
     */
    protected function getNgrams(string $text): array
    {
        $ngrams = [];
        $len = mb_strlen($text);

        if ($len < self::NGRAM_SIZE) {
            // For short strings, use the whole string as an n-gram
            if ($len > 0) {
                $ngrams[$text] = 1;
            }

            return $ngrams;
        }

        for ($i = 0; $i <= $len - self::NGRAM_SIZE; $i++) {
            $ngram = mb_substr($text, $i, self::NGRAM_SIZE);

            if (! isset($ngrams[$ngram])) {
                $ngrams[$ngram] = 0;
            }

            $ngrams[$ngram]++;
        }

        return $ngrams;
    }

    /**
     * Calculate Levenshtein distance for arrays (word-level).
     *
     * @param  array<string>  $a
     * @param  array<string>  $b
     */
    protected function arrayLevenshtein(array $a, array $b): int
    {
        $m = count($a);
        $n = count($b);

        if ($m === 0) {
            return $n;
        }
        if ($n === 0) {
            return $m;
        }

        // Initialize the distance matrix
        $d = [];

        for ($i = 0; $i <= $m; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $n; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $cost = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;

                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,      // Deletion
                    $d[$i][$j - 1] + 1,      // Insertion
                    $d[$i - 1][$j - 1] + $cost // Substitution
                );
            }
        }

        return $d[$m][$n];
    }

    /**
     * Suggest translations for multiple source texts.
     *
     * @param  array<string>  $sources  Source texts to match
     * @param  string  $sourceLocale  Source locale
     * @param  string  $targetLocale  Target locale
     * @param  float|null  $minSimilarity  Minimum similarity threshold
     * @return array<string, array{entry: TranslationMemoryEntry, similarity: float, confidence: float}|null>
     */
    public function suggestBatch(
        array $sources,
        string $sourceLocale,
        string $targetLocale,
        ?float $minSimilarity = null,
    ): array {
        $results = [];

        foreach ($sources as $source) {
            $results[$source] = $this->getBestMatch($source, $sourceLocale, $targetLocale, $minSimilarity);
        }

        return $results;
    }

    /**
     * Get similarity thresholds for categorizing matches.
     *
     * @return array{exact: float, high: float, medium: float, low: float}
     */
    public static function getThresholds(): array
    {
        return [
            'exact' => 1.0,
            'high' => 0.9,
            'medium' => 0.75,
            'low' => 0.6,
        ];
    }

    /**
     * Categorize a similarity score.
     *
     * @return string One of: 'exact', 'high', 'medium', 'low', 'none'
     */
    public static function categorizeSimilarity(float $similarity): string
    {
        $thresholds = self::getThresholds();

        if ($similarity >= $thresholds['exact']) {
            return 'exact';
        }
        if ($similarity >= $thresholds['high']) {
            return 'high';
        }
        if ($similarity >= $thresholds['medium']) {
            return 'medium';
        }
        if ($similarity >= $thresholds['low']) {
            return 'low';
        }

        return 'none';
    }
}
