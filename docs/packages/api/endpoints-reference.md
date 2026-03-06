# API Endpoints Reference

Complete reference for all core-api endpoints. All endpoints follow RESTful conventions with consistent authentication, pagination, filtering, and error handling.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All authenticated endpoints require an API key in the Authorization header:

```http
Authorization: Bearer sk_live_abc123def456...
```

See [Authentication](/packages/api/authentication) for details on creating and managing API keys.

## Common Headers

### Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | Yes* | API key (Bearer token) |
| `Accept` | No | Should be `application/json` |
| `Content-Type` | For POST/PUT | Should be `application/json` |
| `X-Workspace-ID` | Sometimes | Workspace context for multi-tenant endpoints |
| `Idempotency-Key` | No | UUID for safe retries on POST/PUT/DELETE |

*Required for authenticated endpoints

### Response Headers

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Maximum requests allowed in window |
| `X-RateLimit-Remaining` | Requests remaining in current window |
| `X-RateLimit-Reset` | Unix timestamp when limit resets |
| `X-Request-ID` | Unique request identifier for debugging |

## Common Parameters

### Pagination

All list endpoints support pagination:

| Parameter | Type | Default | Max | Description |
|-----------|------|---------|-----|-------------|
| `page` | integer | 1 | - | Page number |
| `per_page` | integer | 25 | 100 | Items per page |

**Response format:**

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 25,
    "to": 25,
    "total": 250
  },
  "links": {
    "first": "https://api.example.com/v1/resource?page=1",
    "last": "https://api.example.com/v1/resource?page=10",
    "prev": null,
    "next": "https://api.example.com/v1/resource?page=2"
  }
}
```

### Filtering

Filter list results with query parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status (varies by resource) |
| `created_after` | ISO 8601 date | Filter by creation date |
| `created_before` | ISO 8601 date | Filter by creation date |
| `updated_after` | ISO 8601 date | Filter by update date |
| `updated_before` | ISO 8601 date | Filter by update date |
| `search` | string | Full-text search (if supported) |

### Sorting

Sort results using the `sort` parameter:

```http
GET /api/v1/resources?sort=-created_at,name
```

- Prefix with `-` for descending order
- Default is ascending order
- Comma-separate multiple fields

### Field Selection

Request specific fields only:

```http
GET /api/v1/resources?fields=id,name,created_at
```

### Includes

Eager-load related resources:

```http
GET /api/v1/resources?include=owner,tags
```

---

## Workspaces

### List Workspaces

```http
GET /api/v1/workspaces
```

**Required scope:** `workspaces:read`

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `per_page` | integer | Items per page |

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "tier": "business",
      "created_at": "2026-01-01T00:00:00Z",
      "updated_at": "2026-01-15T10:30:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### Get Workspace

```http
GET /api/v1/workspaces/{id}
```

**Required scope:** `workspaces:read`

**Path parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Workspace ID |

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "Acme Corporation",
    "slug": "acme-corp",
    "tier": "business",
    "settings": {
      "timezone": "UTC",
      "locale": "en_GB"
    },
    "created_at": "2026-01-01T00:00:00Z",
    "updated_at": "2026-01-15T10:30:00Z"
  }
}
```

**Error responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | `not_found` | Workspace not found |

### Create Workspace

```http
POST /api/v1/workspaces
```

**Required scope:** `workspaces:write`

**Request body:**

```json
{
  "name": "New Workspace",
  "slug": "new-workspace",
  "tier": "pro"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Workspace name (max 255 chars) |
| `slug` | string | No | URL-friendly identifier (auto-generated if not provided) |
| `tier` | string | No | Subscription tier (default: free) |

**Response:** `201 Created`

```json
{
  "message": "Workspace created successfully.",
  "data": {
    "id": 2,
    "name": "New Workspace",
    "slug": "new-workspace",
    "tier": "pro",
    "created_at": "2026-01-15T10:30:00Z"
  }
}
```

**Error responses:**

| Status | Code | Description |
|--------|------|-------------|
| 422 | `validation_failed` | Invalid input data |

### Update Workspace

```http
PATCH /api/v1/workspaces/{id}
```

**Required scope:** `workspaces:write`

**Request body:**

```json
{
  "name": "Updated Name",
  "settings": {
    "timezone": "Europe/London"
  }
}
```

**Response:** `200 OK`

### Delete Workspace

```http
DELETE /api/v1/workspaces/{id}
```

**Required scope:** `workspaces:delete`

**Response:** `204 No Content`

---

## API Keys

### List API Keys

```http
GET /api/v1/api-keys
```

**Required scope:** `api-keys:read`

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Production API Key",
      "prefix": "sk_live_abc",
      "scopes": ["posts:read", "posts:write"],
      "rate_limit_tier": "pro",
      "last_used_at": "2026-01-15T10:30:00Z",
      "expires_at": null,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ]
}
```

Note: The full API key is never returned after creation.

### Get API Key

```http
GET /api/v1/api-keys/{id}
```

**Required scope:** `api-keys:read`

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "Production API Key",
    "prefix": "sk_live_abc",
    "scopes": ["posts:read", "posts:write"],
    "rate_limit_tier": "pro",
    "last_used_at": "2026-01-15T10:30:00Z",
    "expires_at": null,
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

### Create API Key

```http
POST /api/v1/api-keys
```

**Required scope:** `api-keys:write`

**Request body:**

```json
{
  "name": "Mobile App Key",
  "scopes": ["posts:read", "users:read"],
  "rate_limit_tier": "pro",
  "expires_at": "2027-01-01T00:00:00Z"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Key name (max 255 chars) |
| `scopes` | array | No | Permission scopes (default: read, write) |
| `rate_limit_tier` | string | No | Rate limit tier (default: from workspace) |
| `expires_at` | ISO 8601 | No | Expiration date (null = never) |

**Response:** `201 Created`

```json
{
  "message": "API key created successfully.",
  "data": {
    "id": 2,
    "name": "Mobile App Key",
    "key": "sk_live_abc123def456ghi789...",
    "scopes": ["posts:read", "users:read"],
    "rate_limit_tier": "pro",
    "expires_at": "2027-01-01T00:00:00Z",
    "created_at": "2026-01-15T10:30:00Z"
  }
}
```

**Important:** The `key` field is only returned once during creation. Store it securely.

### Rotate API Key

```http
POST /api/v1/api-keys/{id}/rotate
```

**Required scope:** `api-keys:write`

**Request body:**

```json
{
  "grace_period_hours": 24
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `grace_period_hours` | integer | No | Hours both keys work (default: 24) |

**Response:** `200 OK`

```json
{
  "message": "API key rotated successfully.",
  "data": {
    "id": 3,
    "name": "Mobile App Key",
    "key": "sk_live_new123key456...",
    "scopes": ["posts:read", "users:read"],
    "grace_period_ends_at": "2026-01-16T10:30:00Z"
  }
}
```

### Revoke API Key

```http
DELETE /api/v1/api-keys/{id}
```

**Required scope:** `api-keys:delete`

**Response:** `204 No Content`

---

## Webhooks

### List Webhook Endpoints

```http
GET /api/v1/webhooks
```

**Required scope:** `webhooks:read`

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "url": "https://your-app.com/webhooks",
      "events": ["post.created", "post.updated"],
      "is_active": true,
      "success_count": 150,
      "failure_count": 2,
      "last_delivery_at": "2026-01-15T10:30:00Z",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ]
}
```

### Get Webhook Endpoint

```http
GET /api/v1/webhooks/{id}
```

**Required scope:** `webhooks:read`

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "url": "https://your-app.com/webhooks",
    "events": ["post.created", "post.updated"],
    "is_active": true,
    "success_count": 150,
    "failure_count": 2,
    "consecutive_failures": 0,
    "last_delivery_at": "2026-01-15T10:30:00Z",
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

### Create Webhook Endpoint

```http
POST /api/v1/webhooks
```

**Required scope:** `webhooks:write`

**Request body:**

```json
{
  "url": "https://your-app.com/webhooks",
  "events": ["post.created", "post.updated", "post.deleted"],
  "secret": "whsec_abc123def456..."
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | Webhook endpoint URL (HTTPS required) |
| `events` | array | Yes | Events to subscribe to |
| `secret` | string | No | Signing secret (auto-generated if not provided) |

**Response:** `201 Created`

```json
{
  "message": "Webhook endpoint created successfully.",
  "data": {
    "id": 2,
    "url": "https://your-app.com/webhooks",
    "events": ["post.created", "post.updated", "post.deleted"],
    "secret": "whsec_abc123def456...",
    "is_active": true,
    "created_at": "2026-01-15T10:30:00Z"
  }
}
```

**Important:** The `secret` is only returned during creation. Store it securely.

### Update Webhook Endpoint

```http
PATCH /api/v1/webhooks/{id}
```

**Required scope:** `webhooks:write`

**Request body:**

```json
{
  "url": "https://new-url.com/webhooks",
  "events": ["post.*"],
  "is_active": true
}
```

**Response:** `200 OK`

### Delete Webhook Endpoint

```http
DELETE /api/v1/webhooks/{id}
```

**Required scope:** `webhooks:delete`

**Response:** `204 No Content`

### Test Webhook Endpoint

```http
POST /api/v1/webhooks/{id}/test
```

**Required scope:** `webhooks:write`

Sends a test event to the webhook endpoint.

**Response:** `200 OK`

```json
{
  "success": true,
  "status_code": 200,
  "response_time_ms": 145,
  "response_body": "{\"received\": true}"
}
```

**Error response (delivery failed):**

```json
{
  "success": false,
  "status_code": 500,
  "error": "Connection timeout",
  "response_time_ms": 30000
}
```

### List Webhook Deliveries

```http
GET /api/v1/webhooks/{id}/deliveries
```

**Required scope:** `webhooks:read`

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `pending`, `success`, `failed`, `retrying` |
| `page` | integer | Page number |
| `per_page` | integer | Items per page |

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "event_id": "evt_abc123def456",
      "event_type": "post.created",
      "status": "success",
      "response_code": 200,
      "attempt": 1,
      "delivered_at": "2026-01-15T10:30:00Z",
      "created_at": "2026-01-15T10:30:00Z"
    },
    {
      "id": 2,
      "event_id": "evt_xyz789",
      "event_type": "post.updated",
      "status": "retrying",
      "response_code": 500,
      "attempt": 2,
      "next_retry_at": "2026-01-15T10:35:00Z",
      "created_at": "2026-01-15T10:30:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### Retry Webhook Delivery

```http
POST /api/v1/webhooks/{webhook_id}/deliveries/{delivery_id}/retry
```

**Required scope:** `webhooks:write`

Manually retry a failed delivery.

**Response:** `200 OK`

```json
{
  "message": "Delivery queued for retry.",
  "data": {
    "id": 2,
    "status": "pending",
    "attempt": 3
  }
}
```

**Error responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | `cannot_retry` | Delivery already succeeded or max retries reached |

---

## Entitlements

### Check Feature Access

```http
GET /api/v1/entitlements/check
```

**Required scope:** `entitlements:read`

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `feature` | string | Yes | Feature key to check |
| `quantity` | integer | No | Amount to check (default: 1) |

**Response:** `200 OK`

```json
{
  "allowed": true,
  "feature": "posts",
  "current_usage": 45,
  "limit": 100,
  "available": 55
}
```

**Response (limit exceeded):**

```json
{
  "allowed": false,
  "feature": "posts",
  "reason": "LIMIT_EXCEEDED",
  "message": "Post limit exceeded. Used: 100, Limit: 100",
  "current_usage": 100,
  "limit": 100,
  "available": 0,
  "upgrade_url": "https://example.com/upgrade"
}
```

### Record Usage

```http
POST /api/v1/entitlements/usage
```

**Required scope:** `entitlements:write`

**Request body:**

```json
{
  "feature": "api_calls",
  "quantity": 1,
  "metadata": {
    "endpoint": "/api/v1/posts"
  }
}
```

**Response:** `200 OK`

```json
{
  "recorded": true,
  "feature": "api_calls",
  "current_usage": 5001,
  "limit": 10000
}
```

### Get Usage Summary

```http
GET /api/v1/entitlements/summary
```

**Required scope:** `entitlements:read`

Returns usage summary for the authenticated user's workspace.

**Response:** `200 OK`

```json
{
  "data": {
    "workspace_id": 1,
    "tier": "pro",
    "entitlements": {
      "posts": {
        "used": 45,
        "limit": 1000,
        "available": 955,
        "percentage": 4.5
      },
      "api_calls": {
        "used": 5001,
        "limit": 10000,
        "available": 4999,
        "percentage": 50.01,
        "reset_at": "2026-02-01T00:00:00Z"
      },
      "storage": {
        "used": 1073741824,
        "limit": 5368709120,
        "available": 4294967296,
        "percentage": 20,
        "unit": "bytes"
      }
    }
  }
}
```

---

## SEO Reports

### Submit SEO Report

```http
POST /api/v1/seo/report
```

**Required scope:** `seo:write`

**Request body:**

```json
{
  "url": "https://example.com/page",
  "scores": {
    "performance": 85,
    "accessibility": 92,
    "best_practices": 88,
    "seo": 95
  },
  "issues": [
    {
      "type": "missing_alt",
      "severity": "warning",
      "element": "img.hero-image"
    }
  ]
}
```

**Response:** `201 Created`

### Get SEO Issues

```http
GET /api/v1/seo/issues/{workspace_id}
```

**Required scope:** `seo:read`

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "url": "https://example.com/page",
      "issue_type": "missing_alt",
      "severity": "warning",
      "details": {...},
      "created_at": "2026-01-15T10:30:00Z"
    }
  ]
}
```

---

## Pixel Tracking

### Get Pixel Configuration

```http
GET /api/v1/pixel/config
```

**Authentication:** Not required

Returns tracking pixel configuration for the current domain.

**Response:** `200 OK`

```json
{
  "enabled": true,
  "features": {
    "pageviews": true,
    "events": true,
    "sessions": true
  },
  "sample_rate": 1.0
}
```

### Track Event

```http
POST /api/v1/pixel/track
```

**Authentication:** Not required

**Rate limit:** 300 requests per minute

**Request body:**

```json
{
  "event": "pageview",
  "url": "https://example.com/page",
  "referrer": "https://google.com",
  "user_agent": "Mozilla/5.0...",
  "properties": {
    "title": "Page Title"
  }
}
```

**Response:** `200 OK`

```json
{
  "tracked": true
}
```

---

## MCP (Model Context Protocol)

### List MCP Servers

```http
GET /api/v1/mcp/servers
```

**Required scope:** `mcp:read`

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "filesystem",
      "name": "Filesystem Server",
      "description": "File and directory operations",
      "tools": ["read_file", "write_file", "list_directory"]
    }
  ]
}
```

### Get MCP Server

```http
GET /api/v1/mcp/servers/{id}
```

**Required scope:** `mcp:read`

### List Server Tools

```http
GET /api/v1/mcp/servers/{id}/tools
```

**Required scope:** `mcp:read`

**Response:** `200 OK`

```json
{
  "data": [
    {
      "name": "read_file",
      "description": "Read contents of a file",
      "parameters": {
        "path": {
          "type": "string",
          "description": "File path to read",
          "required": true
        }
      }
    }
  ]
}
```

### Call MCP Tool

```http
POST /api/v1/mcp/tools/call
```

**Required scope:** `mcp:write`

**Request body:**

```json
{
  "server": "filesystem",
  "tool": "read_file",
  "arguments": {
    "path": "/path/to/file.txt"
  }
}
```

**Response:** `200 OK`

```json
{
  "result": {
    "content": "File contents here...",
    "size": 1234
  }
}
```

### Get MCP Resource

```http
GET /api/v1/mcp/resources/{uri}
```

**Required scope:** `mcp:read`

The `uri` can include slashes and will be URL-decoded.

---

## Error Responses

All errors follow a consistent format:

### Validation Error (422)

```json
{
  "error": "validation_failed",
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### Not Found (404)

```json
{
  "error": "not_found",
  "message": "Resource not found."
}
```

### Unauthorized (401)

```json
{
  "error": "unauthorized",
  "message": "Invalid or missing API key."
}
```

### Forbidden (403)

```json
{
  "error": "access_denied",
  "message": "Insufficient permissions. Required scope: posts:write"
}
```

### Feature Limit Reached (403)

```json
{
  "error": "feature_limit_reached",
  "message": "You have reached your limit for this feature.",
  "feature": "posts",
  "upgrade_url": "https://example.com/upgrade"
}
```

### Rate Limited (429)

```json
{
  "error": "rate_limit_exceeded",
  "message": "Too many requests. Please retry after 60 seconds.",
  "retry_after": 60,
  "limit": 1000,
  "remaining": 0,
  "reset_at": "2026-01-15T11:00:00Z"
}
```

### Server Error (500)

```json
{
  "error": "server_error",
  "message": "An unexpected error occurred.",
  "request_id": "req_abc123def456"
}
```

---

## Rate Limits

Rate limits vary by tier:

| Tier | Requests/Minute | Burst Allowance |
|------|-----------------|-----------------|
| Free | 60 | None |
| Starter | 1,000 | 20% |
| Pro | 5,000 | 30% |
| Agency | 20,000 | 50% |
| Enterprise | 100,000 | 100% |

Rate limit headers are included in every response:

```http
X-RateLimit-Limit: 5000
X-RateLimit-Remaining: 4892
X-RateLimit-Reset: 1705312260
```

See [Rate Limiting](/packages/api/rate-limiting) for details.

---

## Idempotency

For safe retries on POST, PUT, and DELETE requests, include an idempotency key:

```http
POST /api/v1/posts
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

If the same idempotency key is used within 24 hours:
- Same status code and response body returned
- No duplicate resource created
- Safe to retry failed requests

---

## Learn More

- [Building REST APIs](/packages/api/building-rest-apis) - Tutorial for creating API endpoints
- [Authentication](/packages/api/authentication) - API key management
- [Webhooks](/packages/api/webhooks) - Event notifications
- [Webhook Integration](/packages/api/webhook-integration) - Consumer guide
- [Rate Limiting](/packages/api/rate-limiting) - Understanding rate limits
- [Scopes](/packages/api/scopes) - Permission system
