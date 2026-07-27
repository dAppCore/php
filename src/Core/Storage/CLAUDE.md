# Core\Storage

Cache resilience infrastructure: tiered caching, Redis circuit breaker, and cache warming.

## Key Classes

| Class | Purpose |
|-------|---------|
| `TieredCacheStore` | Multi-tier cascading cache: Memory -> Redis -> Database. Reads check fastest first, promotes hits upward. Writes go to all tiers with tier-specific TTLs |
| `CircuitBreaker` | Prevents cascading failures: Closed (normal) -> Open (skip Redis) -> Half-Open (test recovery). Configurable failure threshold, recovery timeout, success threshold |
| `ResilientRedisStore` | Redis wrapper that falls back gracefully on connection failure |
| `CacheResilienceProvider` | Wires up the resilience stack |
| `TierConfiguration` | Configuration DTO for cache tiers (name, driver, TTL) |
| `CacheWarmer` | Pre-populates cache with frequently accessed data |
| `StorageMetrics` | Tracks cache hits/misses per tier |

### Commands/

- `WarmCacheCommand` -- Artisan command to warm the cache

### Events/

- `RedisFallbackActivated` -- Dispatched when Redis fails and fallback is activated

## Tiered Cache Architecture

```
get("key")
  |
  v
[Memory/Array] -- miss --> [Redis] -- miss --> [Database]
     60s TTL                 1h TTL              24h TTL
```

- On hit at a lower tier, value is promoted to all faster tiers
- Writes propagate to all enabled tiers with per-tier TTLs
- Configuration via `config('core.storage.tiered_cache.tiers')`

## Circuit Breaker States

| State | Behaviour |
|-------|-----------|
| Closed | Normal: requests go to Redis |
| Open | Redis failing: requests go directly to fallback, skip Redis |
| Half-Open | Testing: allows limited requests to check if Redis recovered |

Defaults: 5 failures to open, 30s recovery timeout, 2 successes to close.

## Integration

- `RedisFallbackActivated` event allows monitoring/alerting when Redis goes down
- `StorageMetrics` integrates with the tiered cache for hit/miss tracking
- No Boot.php in this directory -- the resilience stack is wired via `CacheResilienceProvider`
