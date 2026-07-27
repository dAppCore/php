# Storage/Events/ — Storage System Events

## Events

| Event | Fired When | Properties |
|-------|-----------|------------|
| `RedisFallbackActivated` | Redis becomes unavailable and fallback driver is activated | `context`, `errorMessage`, `fallbackDriver` (default: `database`) |

Listeners can use this for alerting, monitoring, or graceful degradation when Redis fails.
