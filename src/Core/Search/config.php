<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Search Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Configure the weights for different types of search matches.
    | Higher weights = higher priority in results.
    |
    */

    'scoring' => [

        /*
        | Weight for exact query match in a field.
        */
        'exact_match' => (int) env('SEARCH_WEIGHT_EXACT', 20),

        /*
        | Weight for query appearing at the start of a field.
        */
        'starts_with' => (int) env('SEARCH_WEIGHT_STARTS_WITH', 15),

        /*
        | Weight for partial word matches.
        */
        'word_match' => (int) env('SEARCH_WEIGHT_WORD', 5),

        /*
        | Weight reduction per field position.
        | First field gets full weight, subsequent fields get reduced weight.
        */
        'field_position_factor' => (float) env('SEARCH_FIELD_POSITION_FACTOR', 2.0),

        /*
        | Minimum word length to count as a match.
        */
        'min_word_length' => (int) env('SEARCH_MIN_WORD_LENGTH', 2),

    ],

    /*
    |--------------------------------------------------------------------------
    | Fuzzy Search Settings
    |--------------------------------------------------------------------------
    |
    | Configure fuzzy/typo-tolerant search using Levenshtein distance.
    |
    */

    'fuzzy' => [

        /*
        | Enable fuzzy search by default.
        */
        'enabled' => env('SEARCH_FUZZY_ENABLED', false),

        /*
        | Maximum Levenshtein distance for fuzzy matches.
        | 1 = one character difference (typo tolerance)
        | 2 = two character differences (more lenient)
        */
        'max_distance' => (int) env('SEARCH_FUZZY_MAX_DISTANCE', 2),

        /*
        | Minimum query length to enable fuzzy matching.
        | Short queries with fuzzy matching produce too many false positives.
        */
        'min_query_length' => (int) env('SEARCH_FUZZY_MIN_LENGTH', 4),

        /*
        | Score multiplier for fuzzy matches (0.0 - 1.0).
        | Fuzzy matches should rank lower than exact matches.
        */
        'score_multiplier' => (float) env('SEARCH_FUZZY_SCORE_MULTIPLIER', 0.5),

    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Define searchable API endpoints.
    |
    */

    'api_endpoints' => [
        // Add API endpoints here
        // ['method' => 'GET', 'path' => '/api/example', 'description' => 'Example endpoint'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Suggestions / Autocomplete
    |--------------------------------------------------------------------------
    |
    | Configure search suggestions and autocomplete behaviour.
    | Provides type-ahead suggestions based on popular queries, recent
    | searches, and content.
    |
    */

    'suggestions' => [

        /*
        | Enable search suggestions/autocomplete.
        */
        'enabled' => env('SEARCH_SUGGESTIONS_ENABLED', true),

        /*
        | Maximum number of suggestions to return.
        */
        'max_suggestions' => (int) env('SEARCH_SUGGESTIONS_MAX', 10),

        /*
        | Minimum query length to trigger suggestions.
        | Shorter queries produce too many irrelevant matches.
        */
        'min_query_length' => (int) env('SEARCH_SUGGESTIONS_MIN_LENGTH', 2),

        /*
        | Cache TTL for suggestion results in seconds.
        */
        'cache_ttl' => (int) env('SEARCH_SUGGESTIONS_CACHE_TTL', 300),

        /*
        | Track recent searches per user/session.
        | Enables personalized "recent searches" suggestions.
        */
        'track_recent' => env('SEARCH_SUGGESTIONS_TRACK_RECENT', true),

        /*
        | Maximum recent searches to store per user.
        */
        'max_recent' => (int) env('SEARCH_SUGGESTIONS_MAX_RECENT', 20),

        /*
        | Suggestion sources to use (in priority order).
        | Available: 'popular', 'recent', 'content'
        | - popular: From search analytics (most searched queries)
        | - recent: User's recent searches
        | - content: From searchable content titles/names
        */
        'sources' => ['popular', 'recent', 'content'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Search Analytics
    |--------------------------------------------------------------------------
    |
    | Configure search analytics tracking for query analysis and optimization.
    | Tracks search queries, result counts, and user interactions.
    |
    */

    'analytics' => [

        /*
        | Enable search analytics tracking.
        | When enabled, all search queries are logged for analysis.
        */
        'enabled' => env('SEARCH_ANALYTICS_ENABLED', true),

        /*
        | Track clicks on search results.
        | Enables click-through rate analysis and result optimization.
        */
        'track_clicks' => env('SEARCH_ANALYTICS_TRACK_CLICKS', true),

        /*
        | Track user sessions.
        | When enabled, searches and clicks are grouped by session.
        | May impact privacy - disabled by default.
        */
        'track_sessions' => env('SEARCH_ANALYTICS_TRACK_SESSIONS', false),

        /*
        | Number of days to retain analytics data.
        | Old data is automatically pruned via the prune command.
        | Set to 0 to disable automatic pruning.
        */
        'retention_days' => (int) env('SEARCH_ANALYTICS_RETENTION_DAYS', 90),

        /*
        | Query patterns to exclude from tracking for privacy.
        | Queries containing these strings will not be logged.
        */
        'exclude_patterns' => [
            'password',
            'secret',
            'token',
            'key',
            'credit',
            'ssn',
        ],

    ],

];
