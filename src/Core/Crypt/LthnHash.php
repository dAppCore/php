<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Crypt;

/**
 * LTHN Protocol QuasiHash - Deterministic Identifier Generator.
 *
 * A lightweight, deterministic identifier generator for workspace/domain scoping.
 * Used to create vBucket IDs for CDN path isolation and tenant-scoped identifiers.
 *
 * ## Algorithm Overview
 *
 * The LthnHash algorithm uses a two-step process:
 *
 * 1. **Salt Generation**: The input string is reversed and passed through a
 *    character substitution map (key map), creating a deterministic "salt"
 * 2. **Hashing**: The original input is concatenated with the salt and hashed
 *    using SHA-256 (or xxHash/CRC32 for `fastHash()`)
 *
 * This produces outputs with good distribution properties while maintaining
 * determinism - the same input always produces the same output.
 *
 * ## Available Hash Algorithms
 *
 * | Method | Algorithm | Output Length | Use Case |
 * |--------|-----------|---------------|----------|
 * | `hash()` | SHA-256 | 64 hex chars (256 bits) | Default, high quality |
 * | `shortHash()` | SHA-256 truncated | 16-32 hex chars | Space-constrained IDs |
 * | `fastHash()` | xxHash or CRC32 | 8-16 hex chars | High-throughput scenarios |
 * | `vBucketId()` | SHA-256 | 64 hex chars | CDN path isolation |
 * | `toInt()` | SHA-256 -> int | 60 bits | Sharding/partitioning |
 *
 * ## Security Properties
 *
 * This is a "QuasiHash" - a deterministic identifier generator, NOT a cryptographic hash.
 *
 * **What it provides:**
 * - Deterministic output: same input always produces same output
 * - Uniform distribution: outputs are evenly distributed across the hash space
 * - Avalanche effect: small input changes produce significantly different outputs
 * - Collision resistance proportional to output length (see table below)
 *
 * **What it does NOT provide:**
 * - Pre-image resistance: attackers can potentially reverse the hash
 * - Cryptographic security: the key map is not a secret
 * - Protection against brute force: short hashes can be enumerated
 *
 * ## Collision Resistance by Length
 *
 * | Length | Bits | Collision Probability (10k items) | Use Case |
 * |--------|------|-----------------------------------|----------|
 * | 16     | 64   | ~1 in 3.4 billion                 | Internal IDs, low-volume |
 * | 24     | 96   | ~1 in 79 quintillion              | Cross-system IDs |
 * | 32     | 128  | ~1 in 3.4e38                      | Long-term storage |
 * | 64     | 256  | Negligible                        | Maximum security |
 *
 * ## Performance Considerations
 *
 * For short inputs (< 64 bytes), the default SHA-256 implementation is suitable
 * for most use cases. For extremely high-throughput scenarios with many short
 * strings, consider using `fastHash()` which uses xxHash (when available) or
 * a CRC32-based approach for better performance.
 *
 * Benchmark reference (typical values, YMMV):
 * - SHA-256: ~300k hashes/sec for short strings
 * - xxHash (via hash extension): ~2M hashes/sec for short strings
 * - CRC32: ~1.5M hashes/sec for short strings
 *
 * Use `benchmark()` to measure actual performance on your system.
 *
 * ## Key Rotation
 *
 * The class supports multiple key maps for rotation. When verifying, all registered
 * key maps are tried in order (newest first). This allows gradual migration:
 *
 * 1. Add new key map with `addKeyMap()`
 * 2. New hashes use the new key map
 * 3. Verification tries new key first, falls back to old
 * 4. After migration period, remove old key map with `removeKeyMap()`
 *
 * ## Usage Examples
 *
 * ```php
 * // Generate a vBucket ID for CDN path isolation
 * $vbucket = LthnHash::vBucketId('workspace.example.com');
 * // => "a7b3c9d2e1f4g5h6..."
 *
 * // Generate a short ID for internal use
 * $shortId = LthnHash::shortHash('user-12345', LthnHash::MEDIUM_LENGTH);
 * // => "a7b3c9d2e1f4g5h6i8j9k1l2"
 *
 * // High-throughput scenario
 * $fastId = LthnHash::fastHash('cache-key-123');
 * // => "1a2b3c4d5e6f7g8h"
 *
 * // Sharding: get consistent partition number
 * $partition = LthnHash::toInt('user@example.com', 16);
 * // => 7 (always 7 for this input)
 *
 * // Verify a hash
 * $isValid = LthnHash::verify('user-12345', $shortId);
 * // => true
 * ```
 *
 * ## NOT Suitable For
 *
 * - Password hashing (use `password_hash()` instead)
 * - Security tokens (use `random_bytes()` instead)
 * - Cryptographic signatures
 * - Any security-sensitive operations
 */
class LthnHash
{
    /**
     * Default output length for short hash (16 hex chars = 64 bits).
     */
    public const SHORT_LENGTH = 16;

    /**
     * Medium output length for improved collision resistance (24 hex chars = 96 bits).
     */
    public const MEDIUM_LENGTH = 24;

    /**
     * Long output length for high collision resistance (32 hex chars = 128 bits).
     */
    public const LONG_LENGTH = 32;

    /**
     * Default key map identifier.
     */
    public const DEFAULT_KEY = 'default';

    /**
     * Character-swapping key maps for quasi-salting.
     * Swaps pairs of characters during encoding.
     *
     * Multiple key maps can be registered for key rotation.
     * The first key map is used for new hashes; all are tried during verification.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $keyMaps = [
        'default' => [
            'a' => '7', 'b' => 'x', 'c' => '3', 'd' => 'w',
            'e' => '3', 'f' => 'v', 'g' => '2', 'h' => 'u',
            'i' => '8', 'j' => 't', 'k' => '1', 'l' => 's',
            'm' => '6', 'n' => 'r', 'o' => '4', 'p' => 'q',
            '0' => 'z', '5' => 'y',
            's' => 'z', 't' => '7',
        ],
    ];

    /**
     * The currently active key map identifier for generating new hashes.
     */
    protected static string $activeKey = self::DEFAULT_KEY;

    /**
     * Generate a deterministic quasi-hash from input.
     *
     * Creates a salt by reversing the input and applying character
     * substitution, then hashes input + salt with SHA-256.
     *
     * @param  string  $input  The input string to hash
     * @param  string|null  $keyId  Key map identifier (null uses active key)
     * @return string 64-character SHA-256 hex string
     */
    public static function hash(string $input, ?string $keyId = null): string
    {
        $keyId ??= self::$activeKey;

        // Create salt by reversing input and applying substitution
        $reversed = strrev($input);
        $salt = self::applyKeyMap($reversed, $keyId);

        // Hash input + salt
        return hash('sha256', $input.$salt);
    }

    /**
     * Generate a short hash (prefix of full hash).
     *
     * @param  string  $input  The input string to hash
     * @param  int  $length  Output length in hex characters (default: SHORT_LENGTH)
     */
    public static function shortHash(string $input, int $length = self::SHORT_LENGTH): string
    {
        if ($length < 1 || $length > 64) {
            throw new \InvalidArgumentException('Hash length must be between 1 and 64');
        }

        return substr(self::hash($input), 0, $length);
    }

    /**
     * Generate a vBucket ID for a domain/workspace.
     *
     * Format: 64-character SHA-256 hex string
     *
     * @param  string  $domain  The domain or workspace identifier
     */
    public static function vBucketId(string $domain): string
    {
        // Normalize domain (lowercase, trim)
        $normalized = strtolower(trim($domain));

        return self::hash($normalized);
    }

    /**
     * Verify that a hash matches an input using constant-time comparison.
     *
     * Tries all registered key maps in order (active key first, then others).
     * This supports key rotation: old hashes remain verifiable while new hashes
     * use the current active key.
     *
     * SECURITY NOTE: This method uses hash_equals() for constant-time string
     * comparison, which prevents timing attacks. Regular string comparison
     * (== or ===) can leak information about the hash through timing differences.
     * Always use this method for hash verification rather than direct comparison.
     *
     * @param  string  $input  The original input
     * @param  string  $hash  The hash to verify
     * @return bool True if the hash matches with any registered key map
     */
    public static function verify(string $input, string $hash): bool
    {
        $hashLength = strlen($hash);

        // Try active key first
        $computed = self::hash($input, self::$activeKey);
        if ($hashLength < 64) {
            $computed = substr($computed, 0, $hashLength);
        }
        if (hash_equals($computed, $hash)) {
            return true;
        }

        // Try other key maps for rotation support
        foreach (array_keys(self::$keyMaps) as $keyId) {
            if ($keyId === self::$activeKey) {
                continue;
            }

            $computed = self::hash($input, $keyId);
            if ($hashLength < 64) {
                $computed = substr($computed, 0, $hashLength);
            }
            if (hash_equals($computed, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply the key map character swapping.
     *
     * @param  string  $input  The input string to transform
     * @param  string  $keyId  Key map identifier
     */
    protected static function applyKeyMap(string $input, string $keyId): string
    {
        $keyMap = self::$keyMaps[$keyId] ?? self::$keyMaps[self::DEFAULT_KEY];
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            $output .= $keyMap[$char] ?? $char;
        }

        return $output;
    }

    /**
     * Get the current active key map.
     *
     * @return array<string, string>
     */
    public static function getKeyMap(): array
    {
        return self::$keyMaps[self::$activeKey] ?? self::$keyMaps[self::DEFAULT_KEY];
    }

    /**
     * Get all registered key maps.
     *
     * @return array<string, array<string, string>>
     */
    public static function getKeyMaps(): array
    {
        return self::$keyMaps;
    }

    /**
     * Set a custom key map (replaces the active key map).
     *
     * @param  array<string, string>  $keyMap  Character substitution map
     */
    public static function setKeyMap(array $keyMap): void
    {
        self::$keyMaps[self::$activeKey] = $keyMap;
    }

    /**
     * Add a new key map for rotation.
     *
     * @param  string  $keyId  Unique identifier for this key map
     * @param  array<string, string>  $keyMap  Character substitution map
     * @param  bool  $setActive  Whether to make this the active key for new hashes
     */
    public static function addKeyMap(string $keyId, array $keyMap, bool $setActive = true): void
    {
        self::$keyMaps[$keyId] = $keyMap;

        if ($setActive) {
            self::$activeKey = $keyId;
        }
    }

    /**
     * Remove a key map.
     *
     * Cannot remove the default key map or the currently active key map.
     *
     * @param  string  $keyId  Key map identifier to remove
     *
     * @throws \InvalidArgumentException If attempting to remove default or active key
     */
    public static function removeKeyMap(string $keyId): void
    {
        if ($keyId === self::DEFAULT_KEY) {
            throw new \InvalidArgumentException('Cannot remove the default key map');
        }

        if ($keyId === self::$activeKey) {
            throw new \InvalidArgumentException('Cannot remove the active key map. Set a different active key first.');
        }

        unset(self::$keyMaps[$keyId]);
    }

    /**
     * Get the active key map identifier.
     */
    public static function getActiveKey(): string
    {
        return self::$activeKey;
    }

    /**
     * Set the active key map for generating new hashes.
     *
     * @param  string  $keyId  Key map identifier (must already be registered)
     *
     * @throws \InvalidArgumentException If key map does not exist
     */
    public static function setActiveKey(string $keyId): void
    {
        if (! isset(self::$keyMaps[$keyId])) {
            throw new \InvalidArgumentException("Key map '{$keyId}' does not exist");
        }

        self::$activeKey = $keyId;
    }

    /**
     * Reset to default state.
     *
     * Removes all custom key maps and resets to the default key map.
     */
    public static function reset(): void
    {
        self::$keyMaps = [
            self::DEFAULT_KEY => [
                'a' => '7', 'b' => 'x', 'c' => '3', 'd' => 'w',
                'e' => '3', 'f' => 'v', 'g' => '2', 'h' => 'u',
                'i' => '8', 'j' => 't', 'k' => '1', 'l' => 's',
                'm' => '6', 'n' => 'r', 'o' => '4', 'p' => 'q',
                '0' => 'z', '5' => 'y',
                's' => 'z', 't' => '7',
            ],
        ];
        self::$activeKey = self::DEFAULT_KEY;
    }

    /**
     * Generate a deterministic integer from input.
     * Useful for consistent sharding/partitioning.
     *
     * @param  string  $input  The input string
     * @param  int  $max  Maximum value (exclusive)
     */
    public static function toInt(string $input, int $max = PHP_INT_MAX): int
    {
        $hash = self::hash($input);
        // Use first 15 hex chars (60 bits) for safe int conversion
        $hex = substr($hash, 0, 15);

        return gmp_intval(gmp_mod(gmp_init($hex, 16), $max));
    }

    /**
     * Generate a fast hash for performance-critical operations.
     *
     * Uses xxHash when available (via hash extension), falling back to a
     * CRC32-based approach. This is significantly faster than SHA-256 for
     * short inputs but provides less collision resistance.
     *
     * Best for:
     * - High-throughput scenarios (millions of hashes)
     * - Cache keys and temporary identifiers
     * - Hash table bucketing
     *
     * NOT suitable for:
     * - Long-term storage identifiers
     * - Security-sensitive operations
     * - Cases requiring strong collision resistance
     *
     * @param  string  $input  The input string to hash
     * @param  int  $length  Output length in hex characters (max 16 for xxh64, 8 for crc32)
     * @return string Hex hash string
     */
    public static function fastHash(string $input, int $length = 16): string
    {
        // Apply key map for consistency with standard hash
        $keyId = self::$activeKey;
        $reversed = strrev($input);
        $salted = $input.self::applyKeyMap($reversed, $keyId);

        // Use xxHash if available (PHP 8.1+ with hash extension)
        if (in_array('xxh64', hash_algos(), true)) {
            $hash = hash('xxh64', $salted);

            return substr($hash, 0, min($length, 16));
        }

        // Fallback: combine two CRC32 variants for 16 hex chars
        $crc1 = hash('crc32b', $salted);
        $crc2 = hash('crc32c', strrev($salted));
        $combined = $crc1.$crc2;

        return substr($combined, 0, min($length, 16));
    }

    /**
     * Run a simple benchmark comparing hash algorithms.
     *
     * Returns timing data for hash(), shortHash(), and fastHash() to help
     * choose the appropriate method for your use case.
     *
     * @param  int  $iterations  Number of hash operations to run
     * @param  string|null  $testInput  Input string to hash (default: random 32 chars)
     * @return array{
     *     hash: array{iterations: int, total_ms: float, per_hash_us: float},
     *     shortHash: array{iterations: int, total_ms: float, per_hash_us: float},
     *     fastHash: array{iterations: int, total_ms: float, per_hash_us: float},
     *     fastHash_algorithm: string
     * }
     */
    public static function benchmark(int $iterations = 10000, ?string $testInput = null): array
    {
        $testInput ??= bin2hex(random_bytes(16)); // 32 char test string

        // Benchmark hash()
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            self::hash($testInput.$i);
        }
        $hashTime = (hrtime(true) - $start) / 1e6; // Convert to ms

        // Benchmark shortHash()
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            self::shortHash($testInput.$i);
        }
        $shortHashTime = (hrtime(true) - $start) / 1e6;

        // Benchmark fastHash()
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            self::fastHash($testInput.$i);
        }
        $fastHashTime = (hrtime(true) - $start) / 1e6;

        // Determine which algorithm fastHash is using
        $fastHashAlgo = in_array('xxh64', hash_algos(), true) ? 'xxh64' : 'crc32b+crc32c';

        return [
            'hash' => [
                'iterations' => $iterations,
                'total_ms' => round($hashTime, 2),
                'per_hash_us' => round(($hashTime * 1000) / $iterations, 3),
            ],
            'shortHash' => [
                'iterations' => $iterations,
                'total_ms' => round($shortHashTime, 2),
                'per_hash_us' => round(($shortHashTime * 1000) / $iterations, 3),
            ],
            'fastHash' => [
                'iterations' => $iterations,
                'total_ms' => round($fastHashTime, 2),
                'per_hash_us' => round(($fastHashTime * 1000) / $iterations, 3),
            ],
            'fastHash_algorithm' => $fastHashAlgo,
        ];
    }
}
