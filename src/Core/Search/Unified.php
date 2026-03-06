<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Search;

use Core\Search\Analytics\SearchAnalytics;
use Core\Search\Suggestions\SearchSuggestions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Unified Search - single search across all system components.
 *
 * Searches MCP server tools/resources, API endpoints, patterns, assets,
 * vendor todos, and agent plans.
 *
 * Features:
 * - Configurable scoring weights for different match types
 * - Fuzzy search support using Levenshtein distance
 * - Prioritizes exact matches over partial matches
 */
class Unified
{
    public const TYPE_MCP_TOOL = 'mcp_tool';

    public const TYPE_MCP_RESOURCE = 'mcp_resource';

    public const TYPE_API_ENDPOINT = 'api_endpoint';

    public const TYPE_PATTERN = 'pattern';

    public const TYPE_ASSET = 'asset';

    public const TYPE_TODO = 'todo';

    public const TYPE_PLAN = 'plan';

    /**
     * Default cache TTL for search results in seconds.
     */
    protected const CACHE_TTL = 60;

    /**
     * Maximum allowed wildcards in a search query.
     */
    protected const MAX_WILDCARDS = 3;

    /**
     * Whether fuzzy search is enabled for this instance.
     */
    protected bool $fuzzyEnabled;

    /**
     * Scoring configuration.
     *
     * @var array<string, int|float>
     */
    protected array $scoringConfig;

    /**
     * Fuzzy search configuration.
     *
     * @var array<string, mixed>
     */
    protected array $fuzzyConfig;

    public function __construct()
    {
        $this->scoringConfig = [
            'exact_match' => config('search.scoring.exact_match', 20),
            'starts_with' => config('search.scoring.starts_with', 15),
            'word_match' => config('search.scoring.word_match', 5),
            'field_position_factor' => config('search.scoring.field_position_factor', 2.0),
            'min_word_length' => config('search.scoring.min_word_length', 2),
        ];

        $this->fuzzyConfig = [
            'enabled' => config('search.fuzzy.enabled', false),
            'max_distance' => config('search.fuzzy.max_distance', 2),
            'min_query_length' => config('search.fuzzy.min_query_length', 4),
            'score_multiplier' => config('search.fuzzy.score_multiplier', 0.5),
        ];

        $this->fuzzyEnabled = $this->fuzzyConfig['enabled'];
    }

    /**
     * Enable fuzzy search for this query.
     */
    public function fuzzy(bool $enabled = true): static
    {
        $this->fuzzyEnabled = $enabled;

        return $this;
    }

    /**
     * Set the maximum Levenshtein distance for fuzzy matches.
     */
    public function maxDistance(int $distance): static
    {
        $this->fuzzyConfig['max_distance'] = $distance;

        return $this;
    }

    /**
     * Perform unified search across all sources.
     */
    public function search(string $query, array $types = [], int $limit = 50): Collection
    {
        $query = strtolower(trim($query));

        if (empty($query)) {
            return collect();
        }

        $cacheKey = $this->buildCacheKey($query, $types, $limit);
        $startTime = microtime(true);

        $results = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $types, $limit) {
            return $this->executeSearch($query, $types, $limit);
        });

        // Track search analytics
        $this->trackSearchAnalytics($query, $results->count(), $types, $startTime);

        // Record for suggestions
        $this->recordForSuggestions($query);

        return $results;
    }

    /**
     * Get search suggestions/autocomplete for a partial query.
     *
     * @param  string  $query  The partial search query
     * @param  int|null  $limit  Maximum suggestions to return
     * @param  array<string>|null  $sources  Suggestion sources to use
     * @return Collection<int, array{text: string, type: string, score: float, metadata: array}>
     */
    public function suggest(string $query, ?int $limit = null, ?array $sources = null): Collection
    {
        try {
            $suggestions = app(SearchSuggestions::class);

            return $suggestions->suggest($query, $limit, $sources);
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Record a search query for the suggestions system.
     */
    protected function recordForSuggestions(string $query): void
    {
        if (! config('search.suggestions.enabled', true)) {
            return;
        }

        try {
            $suggestions = app(SearchSuggestions::class);
            $suggestions->recordRecentSearch($query);
        } catch (\Exception $e) {
            // Don't let suggestion tracking break search
        }
    }

    /**
     * Track search query in analytics.
     */
    protected function trackSearchAnalytics(
        string $query,
        int $resultCount,
        array $types,
        float $startTime
    ): void {
        if (! config('search.analytics.enabled', true)) {
            return;
        }

        try {
            $analytics = app(SearchAnalytics::class);
            $duration = (microtime(true) - $startTime) * 1000;

            $analytics->trackQuery(
                $query,
                $resultCount,
                $types,
                $duration
            );
        } catch (\Exception $e) {
            // Don't let analytics failures break search
        }
    }

    /**
     * Track a click on a search result.
     */
    public function trackClick(
        string $query,
        string $resultType,
        string $resultId,
        int $position
    ): void {
        if (! config('search.analytics.enabled', true)) {
            return;
        }

        try {
            $analytics = app(SearchAnalytics::class);
            $analytics->trackClick($query, $resultType, $resultId, $position);
        } catch (\Exception $e) {
            // Don't let analytics failures break the application
        }
    }

    /**
     * Build a cache key for the search query.
     */
    protected function buildCacheKey(string $query, array $types, int $limit): string
    {
        $typesHash = empty($types) ? 'all' : md5(implode(',', $types));

        return "unified_search:{$typesHash}:{$limit}:".md5($query);
    }

    /**
     * Execute the actual search across all sources.
     */
    protected function executeSearch(string $query, array $types, int $limit): Collection
    {
        $results = collect();

        $searchAll = empty($types);

        if ($searchAll || in_array(self::TYPE_MCP_TOOL, $types)) {
            $results = $results->merge($this->searchMcpTools($query));
        }

        if ($searchAll || in_array(self::TYPE_MCP_RESOURCE, $types)) {
            $results = $results->merge($this->searchMcpResources($query));
        }

        if ($searchAll || in_array(self::TYPE_API_ENDPOINT, $types)) {
            $results = $results->merge($this->searchApiEndpoints($query));
        }

        if ($searchAll || in_array(self::TYPE_PATTERN, $types)) {
            $results = $results->merge($this->searchPatterns($query));
        }

        if ($searchAll || in_array(self::TYPE_ASSET, $types)) {
            $results = $results->merge($this->searchAssets($query));
        }

        if ($searchAll || in_array(self::TYPE_TODO, $types)) {
            $results = $results->merge($this->searchTodos($query));
        }

        if ($searchAll || in_array(self::TYPE_PLAN, $types)) {
            $results = $results->merge($this->searchPlans($query));
        }

        return $results
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Escape special LIKE wildcards and limit wildcard count to prevent DoS.
     *
     * SQL LIKE wildcards (% and _) in user input are escaped to prevent
     * expensive full-table scans from malicious patterns like "%%%%".
     */
    protected function escapeLikeQuery(string $query): string
    {
        $wildcardCount = substr_count($query, '%') + substr_count($query, '_');

        if ($wildcardCount > self::MAX_WILDCARDS) {
            $query = str_replace(['%', '_'], '', $query);
        } else {
            $query = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        }

        return $query;
    }

    /**
     * Search MCP tools from server YAML files.
     */
    protected function searchMcpTools(string $query): Collection
    {
        $results = collect();
        $servers = $this->loadMcpServers();

        foreach ($servers as $server) {
            foreach ($server['tools'] ?? [] as $tool) {
                $name = strtolower($tool['name'] ?? '');
                $purpose = strtolower($tool['purpose'] ?? '');

                $score = $this->calculateScore($query, [$name, $purpose]);

                if ($score > 0) {
                    $results->push([
                        'type' => self::TYPE_MCP_TOOL,
                        'icon' => 'wrench',
                        'title' => $tool['name'],
                        'subtitle' => $server['name'],
                        'description' => $tool['purpose'] ?? '',
                        'url' => '#mcp-'.$server['id'].'-'.$tool['name'],
                        'score' => $score,
                        'meta' => [
                            'server_id' => $server['id'],
                            'parameters' => array_keys($tool['parameters'] ?? []),
                        ],
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Search MCP resources from server YAML files.
     */
    protected function searchMcpResources(string $query): Collection
    {
        $results = collect();
        $servers = $this->loadMcpServers();

        foreach ($servers as $server) {
            foreach ($server['resources'] ?? [] as $resource) {
                $name = strtolower($resource['name'] ?? '');
                $uri = strtolower($resource['uri'] ?? '');
                $description = strtolower($resource['description'] ?? '');

                $score = $this->calculateScore($query, [$name, $uri, $description]);

                if ($score > 0) {
                    $results->push([
                        'type' => self::TYPE_MCP_RESOURCE,
                        'icon' => 'document',
                        'title' => $resource['name'] ?? $resource['uri'],
                        'subtitle' => $server['name'],
                        'description' => $resource['description'] ?? '',
                        'url' => '#mcp-'.$server['id'],
                        'score' => $score,
                        'meta' => [
                            'server_id' => $server['id'],
                            'uri' => $resource['uri'],
                        ],
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Search API endpoints from config.
     */
    protected function searchApiEndpoints(string $query): Collection
    {
        $results = collect();
        $endpoints = $this->loadApiEndpoints();

        foreach ($endpoints as $endpoint) {
            $path = strtolower($endpoint['path']);
            $description = strtolower($endpoint['description']);

            $score = $this->calculateScore($query, [$path, $description, $endpoint['method']]);

            if ($score > 0) {
                $results->push([
                    'type' => self::TYPE_API_ENDPOINT,
                    'icon' => 'globe-alt',
                    'title' => "{$endpoint['method']} {$endpoint['path']}",
                    'subtitle' => 'API Endpoint',
                    'description' => $endpoint['description'],
                    'url' => '/docs/api',
                    'score' => $score,
                    'meta' => [
                        'method' => $endpoint['method'],
                        'path' => $endpoint['path'],
                    ],
                ]);
            }
        }

        return $results;
    }

    /**
     * Search patterns from database.
     */
    protected function searchPatterns(string $query): Collection
    {
        if (! class_exists(\Core\Mod\Uptelligence\Models\Pattern::class)) {
            return collect();
        }

        $escaped = $this->escapeLikeQuery($query);

        try {
            return \Core\Mod\Uptelligence\Models\Pattern::where('name', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%")
                ->orWhere('category', 'like', "%{$escaped}%")
                ->limit(20)
                ->get()
                ->map(fn ($pattern) => [
                    'type' => self::TYPE_PATTERN,
                    'icon' => 'puzzle-piece',
                    'title' => $pattern->name,
                    'subtitle' => $pattern->category,
                    'description' => $pattern->description ?? '',
                    'url' => '#',
                    'score' => $this->calculateScore($query, [
                        strtolower($pattern->name),
                        strtolower($pattern->description ?? ''),
                    ]),
                    'meta' => [
                        'id' => $pattern->id,
                        'category' => $pattern->category,
                    ],
                ]);
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Search assets from database.
     */
    protected function searchAssets(string $query): Collection
    {
        if (! class_exists(\Core\Mod\Uptelligence\Models\Asset::class)) {
            return collect();
        }

        $escaped = $this->escapeLikeQuery($query);

        try {
            return \Core\Mod\Uptelligence\Models\Asset::where('name', 'like', "%{$escaped}%")
                ->orWhere('slug', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%")
                ->limit(20)
                ->get()
                ->map(fn ($asset) => [
                    'type' => self::TYPE_ASSET,
                    'icon' => 'cube',
                    'title' => $asset->name,
                    'subtitle' => $asset->type,
                    'description' => $asset->description ?? '',
                    'url' => '#',
                    'score' => $this->calculateScore($query, [
                        strtolower($asset->name),
                        strtolower($asset->slug),
                    ]),
                    'meta' => [
                        'id' => $asset->id,
                        'type' => $asset->type,
                        'version' => $asset->installed_version,
                    ],
                ]);
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Search upstream todos from database.
     */
    protected function searchTodos(string $query): Collection
    {
        if (! class_exists(\Core\Mod\Uptelligence\Models\UpstreamTodo::class)) {
            return collect();
        }

        $escaped = $this->escapeLikeQuery($query);

        try {
            return \Core\Mod\Uptelligence\Models\UpstreamTodo::where('title', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%")
                ->limit(20)
                ->get()
                ->map(fn ($todo) => [
                    'type' => self::TYPE_TODO,
                    'icon' => 'clipboard-list',
                    'title' => $todo->title,
                    'subtitle' => $todo->vendor?->name ?? 'Vendor Todo',
                    'description' => Str::limit($todo->description ?? '', 100),
                    'url' => '#',
                    'score' => $this->calculateScore($query, [
                        strtolower($todo->title),
                        strtolower($todo->description ?? ''),
                    ]),
                    'meta' => [
                        'id' => $todo->id,
                        'status' => $todo->status,
                        'priority' => $todo->priority,
                    ],
                ]);
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Search agent plans from database.
     */
    protected function searchPlans(string $query): Collection
    {
        if (! class_exists(\Core\Mod\Agentic\Models\AgentPlan::class)) {
            return collect();
        }

        $escaped = $this->escapeLikeQuery($query);

        try {
            return \Core\Mod\Agentic\Models\AgentPlan::where('title', 'like', "%{$escaped}%")
                ->orWhere('slug', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%")
                ->limit(20)
                ->get()
                ->map(fn ($plan) => [
                    'type' => self::TYPE_PLAN,
                    'icon' => 'map',
                    'title' => $plan->title,
                    'subtitle' => "Plan: {$plan->status}",
                    'description' => $plan->description ?? '',
                    'url' => '#',
                    'score' => $this->calculateScore($query, [
                        strtolower($plan->title),
                        strtolower($plan->slug),
                    ]),
                    'meta' => [
                        'slug' => $plan->slug,
                        'status' => $plan->status,
                        'progress' => $plan->getProgress(),
                    ],
                ]);
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Calculate relevance score for a result.
     *
     * Scoring is based on configurable weights:
     * - Exact match: Highest priority (query found exactly in field)
     * - Starts with: High priority (field begins with query)
     * - Word match: Medium priority (individual words match)
     * - Fuzzy match: Lower priority (similar but not exact)
     *
     * Earlier fields in the array receive higher scores.
     */
    protected function calculateScore(string $query, array $fields): float
    {
        $score = 0.0;
        $words = array_filter(explode(' ', $query), fn ($w) => strlen($w) >= $this->scoringConfig['min_word_length']);
        $positionFactor = $this->scoringConfig['field_position_factor'];

        foreach ($fields as $index => $field) {
            if (empty($field)) {
                continue;
            }

            // Calculate position multiplier (earlier fields get higher scores)
            // Field 0: 1.0, Field 1: 0.5, Field 2: 0.33, etc.
            $positionMultiplier = 1.0 / (1 + ($index * $positionFactor / 10));

            // Check for exact match (highest priority)
            if ($field === $query) {
                $score += $this->scoringConfig['exact_match'] * 1.5 * $positionMultiplier;
            } elseif (str_contains($field, $query)) {
                $score += $this->scoringConfig['exact_match'] * $positionMultiplier;
            }

            // Check if field starts with query
            if (str_starts_with($field, $query)) {
                $score += $this->scoringConfig['starts_with'] * $positionMultiplier;
            }

            // Word-level matching
            $fieldWords = preg_split('/[\s\-_\.]+/', $field);
            foreach ($words as $word) {
                // Exact word match in field
                if (in_array($word, $fieldWords, true)) {
                    $score += $this->scoringConfig['word_match'] * 1.5 * $positionMultiplier;
                } elseif (str_contains($field, $word)) {
                    // Partial word match
                    $score += $this->scoringConfig['word_match'] * $positionMultiplier;
                } elseif ($this->fuzzyEnabled && $this->tryFuzzyMatch($word, $fieldWords)) {
                    // Fuzzy word match
                    $score += $this->scoringConfig['word_match'] * $this->fuzzyConfig['score_multiplier'] * $positionMultiplier;
                }
            }

            // Fuzzy match for the entire query
            if ($this->fuzzyEnabled && strlen($query) >= $this->fuzzyConfig['min_query_length']) {
                foreach ($fieldWords as $fieldWord) {
                    if ($this->isFuzzyMatch($query, $fieldWord)) {
                        $score += $this->scoringConfig['exact_match'] * $this->fuzzyConfig['score_multiplier'] * $positionMultiplier;
                        break;
                    }
                }
            }
        }

        return $score;
    }

    /**
     * Try to find a fuzzy match for a word in a list of field words.
     */
    protected function tryFuzzyMatch(string $word, array $fieldWords): bool
    {
        if (strlen($word) < $this->fuzzyConfig['min_query_length']) {
            return false;
        }

        foreach ($fieldWords as $fieldWord) {
            if ($this->isFuzzyMatch($word, $fieldWord)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two strings are a fuzzy match using Levenshtein distance.
     */
    protected function isFuzzyMatch(string $a, string $b): bool
    {
        // Skip if either string is too short
        if (strlen($a) < 3 || strlen($b) < 3) {
            return false;
        }

        // Calculate allowed distance based on string length
        // Longer strings can have more typos
        $maxAllowed = min(
            $this->fuzzyConfig['max_distance'],
            (int) floor(strlen($a) / 3) // Allow 1 typo per 3 characters
        );

        if ($maxAllowed < 1) {
            return false;
        }

        $distance = levenshtein($a, $b);

        return $distance > 0 && $distance <= $maxAllowed;
    }

    /**
     * Load all MCP servers from YAML files.
     */
    protected function loadMcpServers(): array
    {
        return Cache::remember('unified_search:mcp_servers', 300, function () {
            $servers = [];

            $registryPath = resource_path('mcp/registry.yaml');
            if (! file_exists($registryPath)) {
                return $servers;
            }

            $registry = Yaml::parseFile($registryPath);

            foreach ($registry['servers'] ?? [] as $ref) {
                $serverPath = resource_path("mcp/servers/{$ref['id']}.yaml");
                if (file_exists($serverPath)) {
                    $servers[] = Yaml::parseFile($serverPath);
                }
            }

            return $servers;
        });
    }

    /**
     * Load API endpoints from config.
     */
    protected function loadApiEndpoints(): array
    {
        return config('core.search.api_endpoints', []);
    }

    /**
     * Get available search types for filtering.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_MCP_TOOL => ['name' => 'MCP Tools', 'icon' => 'wrench'],
            self::TYPE_MCP_RESOURCE => ['name' => 'MCP Resources', 'icon' => 'document'],
            self::TYPE_API_ENDPOINT => ['name' => 'API Endpoints', 'icon' => 'globe-alt'],
            self::TYPE_PATTERN => ['name' => 'Patterns', 'icon' => 'puzzle-piece'],
            self::TYPE_ASSET => ['name' => 'Assets', 'icon' => 'cube'],
            self::TYPE_TODO => ['name' => 'Todos', 'icon' => 'clipboard-list'],
            self::TYPE_PLAN => ['name' => 'Plans', 'icon' => 'map'],
        ];
    }
}
