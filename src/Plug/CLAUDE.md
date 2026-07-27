# Plug -- Plugin System for External Service Integrations

`Core\Plug` is an operation-based plugin architecture for connecting to external services (social networks, Web3, content platforms, chat, business tools). Each provider is split into discrete operation classes rather than monolithic adapters.

## Namespace

`Core\Plug` -- autoloaded from `src/Plug/`.

## Architecture

Providers are organised by category and operation:

```
Plug/
  Social/
    Twitter/
      Auth.php       # implements Authenticable
      Post.php       # implements Postable
      Delete.php     # implements Deletable
      Media.php      # implements MediaUploadable
    Bluesky/
      Auth.php
      Post.php
  Web3/
  Content/
  Chat/
  Business/
```

Usage:
```php
use Core\Plug\Social\Twitter\Auth;
use Core\Plug\Social\Twitter\Post;

$auth = new Auth($clientId, $clientSecret, $redirectUrl);
$post = (new Post())->withToken($token);
```

## Key Classes

### Boot (ServiceProvider)

Registers `Registry` as a singleton with alias `plug.registry`. Pure library module -- no routes, views, or migrations.

### Registry

Auto-discovers providers from directory structure. Scans categories: `Social`, `Web3`, `Content`, `Chat`, `Business`.

**Public API:**

| Method | Purpose |
|--------|---------|
| `discover()` | Scans category directories for provider folders. Idempotent. |
| `register(id, category, name, namespace)` | Programmatic registration (for external packages). |
| `identifiers()` | All registered provider identifiers (lowercase). |
| `has(identifier)` | Check if provider exists. |
| `get(identifier)` | Get provider metadata (category, name, namespace, path). |
| `supports(identifier, operation)` | Check if provider has an operation class. |
| `operation(identifier, operation)` | Get the FQCN for a provider's operation class. |
| `all()` | All providers as Collection. |
| `byCategory(category)` | Provider identifiers filtered by category. |
| `withCapability(operation)` | Providers that support a specific operation. |
| `displayName(identifier)` | Human-readable name (from Auth::name() or directory name). |

### Response

Standardised response for all Plug operations. Immutable value object.

**Properties:** `status` (Status enum), `context` (array), `rateLimitApproaching` (bool), `retryAfter` (int).

**Methods:** `isOk()`, `hasError()`, `isUnauthorized()`, `isRateLimited()`, `id()`, `get(key)`, `getMessage()`, `retryAfter()`, `toArray()`. Supports magic `__get` for context values.

## Contracts (interfaces)

| Contract | Methods | Purpose |
|----------|---------|---------|
| `Authenticable` | `identifier()`, `name()`, `getAuthUrl()`, `requestAccessToken(params)`, `getAccount()` | OAuth2/API key/credential auth flows |
| `Postable` | `publish(text, media, params)` | Content publishing |
| `Readable` | `get(id)`, `list(params)` | Read posts/content |
| `Commentable` | `comment(text, postId, params)` | Reply to content |
| `Deletable` | `delete(id)` | Delete content |
| `MediaUploadable` | `upload(item)` | Upload media files |
| `Listable` | `listEntities()` | List pages/boards/publications (target selection) |
| `Refreshable` | `refresh()` | Refresh expired access tokens |

## Concerns (traits)

| Trait | Purpose |
|-------|---------|
| `BuildsResponse` | Response factory helpers: `ok()`, `error()`, `unauthorized()`, `rateLimit()`, `noContent()`, `fromHttp()`. The `fromHttp()` method auto-maps HTTP status codes (401, 429) to correct Response types. |
| `ManagesTokens` | Token lifecycle: `withToken()`, `getToken()`, `accessToken()`, `hasRefreshToken()`, `refreshToken()`, `tokenExpiresSoon(minutes)`, `tokenExpired()`. Fluent interface via `withToken()`. |
| `UsesHttp` | HTTP client helpers: `http()` (configured PendingRequest with 30s timeout, JSON accept, User-Agent), `buildUrl()`. Override `defaultHeaders()` for provider-specific headers. |

## Enum

`Status` -- string-backed: `OK`, `ERROR`, `UNAUTHORIZED`, `RATE_LIMITED`, `NO_CONTENT`.

## Integration Pattern

1. Create provider directory under the appropriate category
2. Implement operation classes using contracts + concerns
3. Registry auto-discovers on first access (or register programmatically)
4. All operations return `Response` objects for consistent error handling

## Categories

`Social`, `Web3`, `Content`, `Chat`, `Business`
