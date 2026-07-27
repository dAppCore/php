# Crypt

Encryption utilities: encrypted Eloquent casts and LTHN QuasiHash identifier generator.

## What It Does

Two independent tools:

1. **EncryptArrayObject** -- Eloquent cast that encrypts/decrypts array data transparently using Laravel's `Crypt` facade
2. **LthnHash** -- Deterministic identifier generator for workspace scoping, vBucket CDN paths, and consistent sharding

## Key Classes

| Class | Purpose |
|-------|---------|
| `EncryptArrayObject` | `CastsAttributes` implementation. Encrypts arrays as JSON+AES on write, decrypts on read. Fails gracefully (returns null + logs warning) |
| `LthnHash` | Static utility: `hash()`, `shortHash()`, `fastHash()`, `vBucketId()`, `toInt()`, `verify()`, `benchmark()`. Supports key rotation |

## EncryptArrayObject Usage

```php
class ApiCredential extends Model {
    protected $casts = ['secrets' => EncryptArrayObject::class];
}
$model->secrets['api_key'] = 'sk_live_xxx'; // encrypted in DB
```

## LthnHash API

| Method | Output | Use Case |
|--------|--------|----------|
| `hash($input)` | 64 hex chars (SHA-256) | Default, high quality |
| `shortHash($input, $len)` | 16-32 hex chars | Space-constrained IDs |
| `fastHash($input)` | 8-16 hex chars (xxHash/CRC32) | High-throughput |
| `vBucketId($domain)` | 64 hex chars | CDN path isolation |
| `toInt($input, $max)` | int (60 bits) | Sharding/partitioning |
| `verify($input, $hash)` | bool | Constant-time comparison, tries all key maps |
| `benchmark($iterations)` | timing array | Performance measurement |

## Algorithm

1. Reverse input, apply character substitution map (key map)
2. Concatenate original + substituted string
3. Hash with SHA-256 (or xxHash/CRC32 for `fastHash`)

## Key Rotation

```php
LthnHash::addKeyMap('v2', $newMap, setActive: true);
// New hashes use v2, verify() tries v2 first then falls back to older maps
LthnHash::removeKeyMap('v1'); // after migration
```

## NOT For

- Password hashing (use `password_hash()`)
- Security tokens (use `random_bytes()`)
- Cryptographic signatures

## Integration

- `CdnUrlBuilder::vBucketId()` delegates to `LthnHash::vBucketId()`
- `verify()` uses `hash_equals()` for timing-attack resistance
- `fastHash()` auto-selects xxh64 (PHP 8.1+) or CRC32b+CRC32c fallback
- `toInt()` uses GMP for safe large-integer modular arithmetic
