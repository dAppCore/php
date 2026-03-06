# API Endpoints Reference

Core PHP Framework provides RESTful APIs for programmatic access to platform resources. All endpoints follow consistent patterns for authentication, pagination, filtering, and error handling.

## Base URL

```
https://your-domain.com/api/v1
```

## Common Parameters

### Pagination

All list endpoints support pagination:

```http
GET /api/v1/resources?page=2&per_page=50
```

**Parameters:**
- `page` (integer) - Page number (default: 1)
- `per_page` (integer) - Items per page (default: 15, max: 100)

**Response includes:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 50,
    "total": 250,
    "last_page": 5
  },
  "links": {
    "first": "https://api.example.com/resources?page=1",
    "last": "https://api.example.com/resources?page=5",
    "prev": "https://api.example.com/resources?page=1",
    "next": "https://api.example.com/resources?page=3"
  }
}
```

### Filtering

Filter list results using query parameters:

```http
GET /api/v1/resources?status=active&created_after=2024-01-01
```

Common filters:
- `status` - Filter by status (varies by resource)
- `created_after` - ISO 8601 date
- `created_before` - ISO 8601 date
- `updated_after` - ISO 8601 date
- `updated_before` - ISO 8601 date
- `search` - Full-text search (if supported)

### Sorting

Sort results using the `sort` parameter:

```http
GET /api/v1/resources?sort=-created_at,name
```

- Prefix with `-` for descending order
- Default is ascending order
- Comma-separate multiple sort fields

### Field Selection

Request specific fields only:

```http
GET /api/v1/resources?fields=id,name,created_at
```

Reduces payload size and improves performance.

### Includes

Eager-load related resources:

```http
GET /api/v1/resources?include=owner,tags,metadata
```

Reduces number of API calls needed.

## Rate Limiting

API requests are rate-limited based on your tier:

| Tier | Requests/Hour | Burst |
|------|--------------|-------|
| Free | 1,000 | 50 |
| Pro | 10,000 | 200 |
| Business | 50,000 | 500 |
| Enterprise | Custom | Custom |

Rate limit headers included in every response:

```http
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9847
X-RateLimit-Reset: 1640995200
```

When rate limit is exceeded, you'll receive a `429 Too Many Requests` response:

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Please retry after 3600 seconds.",
    "retry_after": 3600
  }
}
```

## Idempotency

POST, PATCH, PUT, and DELETE requests support idempotency keys to safely retry requests:

```http
POST /api/v1/resources
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

If the same idempotency key is used within 24 hours:
- Same status code and response body returned
- No duplicate resource created
- Safe to retry failed requests

## Versioning

The API version is included in the URL path:

```
/api/v1/resources
```

When breaking changes are introduced, a new version will be released (e.g., `/api/v2/`). Previous versions are supported for at least 12 months after deprecation notice.

## Workspaces & Namespaces

Multi-tenant resources require workspace and/or namespace context:

```http
GET /api/v1/resources
X-Workspace-ID: 123
X-Namespace-ID: 456
```

Alternatively, use query parameters:

```http
GET /api/v1/resources?workspace_id=123&namespace_id=456
```

See [Namespaces & Entitlements](/security/namespaces) for details on multi-tenancy.

## Webhook Events

Configure webhooks to receive real-time notifications:

```http
POST /api/v1/webhooks
{
  "url": "https://your-app.com/webhooks",
  "events": ["resource.created", "resource.updated"],
  "secret": "whsec_abc123..."
}
```

**Common events:**
- `{resource}.created` - Resource created
- `{resource}.updated` - Resource updated
- `{resource}.deleted` - Resource deleted

**Webhook payload:**
```json
{
  "id": "evt_1234567890",
  "type": "resource.created",
  "created_at": "2024-01-15T10:30:00Z",
  "data": {
    "object": {
      "id": "res_abc123",
      "type": "resource",
      "attributes": {...}
    }
  }
}
```

Webhook requests include HMAC-SHA256 signature in headers:

```http
X-Webhook-Signature: sha256=abc123...
X-Webhook-Timestamp: 1640995200
```

See [Webhook Security](/api/authentication#webhook-signatures) for signature verification.

## Error Handling

All errors follow a consistent format. See [Error Reference](/api/errors) for details.

**Example error response:**

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "email": ["The email field is required."]
    },
    "request_id": "req_abc123"
  }
}
```

## Resource Endpoints

### Core Resources

The following resource types are available:

- **Workspaces** - Multi-tenant workspaces
- **Namespaces** - Service isolation contexts
- **Users** - User accounts
- **API Keys** - API authentication credentials
- **Webhooks** - Webhook endpoints

### Workspace Endpoints

#### List Workspaces

```http
GET /api/v1/workspaces
```

**Response:**
```json
{
  "data": [
    {
      "id": "wks_abc123",
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "tier": "business",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

#### Get Workspace

```http
GET /api/v1/workspaces/{workspace_id}
```

**Response:**
```json
{
  "data": {
    "id": "wks_abc123",
    "name": "Acme Corporation",
    "slug": "acme-corp",
    "tier": "business",
    "settings": {
      "timezone": "UTC",
      "locale": "en_GB"
    },
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Create Workspace

```http
POST /api/v1/workspaces
```

**Request:**
```json
{
  "name": "New Workspace",
  "slug": "new-workspace",
  "tier": "pro"
}
```

**Response:** `201 Created`

#### Update Workspace

```http
PATCH /api/v1/workspaces/{workspace_id}
```

**Request:**
```json
{
  "name": "Updated Name",
  "settings": {
    "timezone": "Europe/London"
  }
}
```

**Response:** `200 OK`

#### Delete Workspace

```http
DELETE /api/v1/workspaces/{workspace_id}
```

**Response:** `204 No Content`

### Namespace Endpoints

#### List Namespaces

```http
GET /api/v1/namespaces
```

**Query parameters:**
- `owner_type` - Filter by owner type (`User` or `Workspace`)
- `workspace_id` - Filter by workspace
- `is_active` - Filter by active status

**Response:**
```json
{
  "data": [
    {
      "id": "ns_abc123",
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Personal Namespace",
      "slug": "personal",
      "owner_type": "User",
      "owner_id": 42,
      "workspace_id": null,
      "is_default": true,
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Get Namespace

```http
GET /api/v1/namespaces/{namespace_id}
```

**Response:**
```json
{
  "data": {
    "id": "ns_abc123",
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Client: Acme Corp",
    "slug": "client-acme",
    "owner_type": "Workspace",
    "owner_id": 10,
    "workspace_id": 10,
    "packages": [
      {
        "id": "pkg_starter",
        "name": "Starter Package",
        "expires_at": null
      }
    ],
    "entitlements": {
      "storage": {
        "used": 1024000000,
        "limit": 5368709120,
        "unit": "bytes"
      },
      "api_calls": {
        "used": 5430,
        "limit": 10000,
        "reset_at": "2024-02-01T00:00:00Z"
      }
    }
  }
}
```

#### Check Entitlement

```http
POST /api/v1/namespaces/{namespace_id}/entitlements/check
```

**Request:**
```json
{
  "feature": "storage",
  "quantity": 1073741824
}
```

**Response:**
```json
{
  "allowed": false,
  "reason": "LIMIT_EXCEEDED",
  "message": "Storage limit exceeded. Used: 1.00 GB, Available: 0.50 GB, Requested: 1.00 GB",
  "current_usage": 1024000000,
  "limit": 5368709120,
  "available": 536870912
}
```

### User Endpoints

#### List Users

```http
GET /api/v1/users
X-Workspace-ID: 123
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "tier": "pro",
      "email_verified_at": "2024-01-01T12:00:00Z",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Get Current User

```http
GET /api/v1/user
```

Returns the authenticated user.

#### Update User

```http
PATCH /api/v1/users/{user_id}
```

**Request:**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com"
}
```

### API Key Endpoints

#### List API Keys

```http
GET /api/v1/api-keys
```

**Response:**
```json
{
  "data": [
    {
      "id": "key_abc123",
      "name": "Production API Key",
      "prefix": "sk_live_",
      "last_used_at": "2024-01-15T10:30:00Z",
      "expires_at": null,
      "scopes": ["read:all", "write:resources"],
      "rate_limit_tier": "business",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Create API Key

```http
POST /api/v1/api-keys
```

**Request:**
```json
{
  "name": "New API Key",
  "scopes": ["read:all"],
  "rate_limit_tier": "pro",
  "expires_at": "2025-01-01T00:00:00Z"
}
```

**Response:**
```json
{
  "data": {
    "id": "key_abc123",
    "name": "New API Key",
    "key": "sk_live_abc123def456...",
    "scopes": ["read:all"],
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

⚠️ **Important:** The `key` field is only returned once during creation. Store it securely.

#### Revoke API Key

```http
DELETE /api/v1/api-keys/{key_id}
```

**Response:** `204 No Content`

### Webhook Endpoints

#### List Webhooks

```http
GET /api/v1/webhooks
```

**Response:**
```json
{
  "data": [
    {
      "id": "wh_abc123",
      "url": "https://your-app.com/webhooks",
      "events": ["resource.created", "resource.updated"],
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Create Webhook

```http
POST /api/v1/webhooks
```

**Request:**
```json
{
  "url": "https://your-app.com/webhooks",
  "events": ["resource.created"],
  "secret": "whsec_abc123..."
}
```

#### Test Webhook

```http
POST /api/v1/webhooks/{webhook_id}/test
```

Sends a test event to the webhook URL.

**Response:**
```json
{
  "success": true,
  "status_code": 200,
  "response_time_ms": 145
}
```

#### Webhook Deliveries

```http
GET /api/v1/webhooks/{webhook_id}/deliveries
```

View delivery history and retry failed deliveries:

```json
{
  "data": [
    {
      "id": "del_abc123",
      "event_type": "resource.created",
      "status": "success",
      "status_code": 200,
      "attempts": 1,
      "delivered_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

## Best Practices

### 1. Use Idempotency Keys

Always use idempotency keys for create/update operations:

```javascript
const response = await fetch('/api/v1/resources', {
  method: 'POST',
  headers: {
    'Idempotency-Key': crypto.randomUUID(),
    'Authorization': `Bearer ${apiKey}`
  },
  body: JSON.stringify(data)
});
```

### 2. Handle Rate Limits

Respect rate limit headers and implement exponential backoff:

```javascript
async function apiRequest(url, options) {
  const response = await fetch(url, options);

  if (response.status === 429) {
    const retryAfter = response.headers.get('X-RateLimit-Reset');
    await sleep(retryAfter * 1000);
    return apiRequest(url, options); // Retry
  }

  return response;
}
```

### 3. Use Field Selection

Request only needed fields to reduce payload size:

```http
GET /api/v1/resources?fields=id,name,status
```

### 4. Batch Operations

When possible, use batch endpoints instead of multiple single requests:

```http
POST /api/v1/resources/batch
{
  "operations": [
    {"action": "create", "data": {...}},
    {"action": "update", "id": "res_123", "data": {...}}
  ]
}
```

### 5. Verify Webhook Signatures

Always verify webhook signatures to ensure authenticity:

```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const hmac = crypto.createHmac('sha256', secret);
  hmac.update(payload);
  const expected = 'sha256=' + hmac.digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}
```

### 6. Store API Keys Securely

- Never commit API keys to version control
- Use environment variables or secrets management
- Rotate keys regularly
- Use separate keys for development/production

### 7. Monitor Usage

Track your API usage to avoid hitting rate limits:

```http
GET /api/v1/usage
```

Returns current usage statistics for your account.

## SDKs & Libraries

Official SDKs available:

- **PHP:** `composer require core-php/sdk`
- **JavaScript/Node.js:** `npm install @core-php/sdk`
- **Python:** `pip install core-php-sdk`

**Example (PHP):**

```php
use CorePhp\SDK\Client;

$client = new Client('sk_live_abc123...');

$workspace = $client->workspaces->create([
    'name' => 'My Workspace',
    'tier' => 'pro',
]);

$namespaces = $client->namespaces->list([
    'workspace_id' => $workspace->id,
]);
```

## Further Reading

- [Authentication](/api/authentication) - API key management and authentication methods
- [Error Handling](/api/errors) - Error codes and debugging
- [Namespaces & Entitlements](/security/namespaces) - Multi-tenancy and feature access
- [Webhooks Guide](#webhook-events) - Setting up webhook endpoints
- [Rate Limiting](#rate-limiting) - Understanding rate limits and tiers
