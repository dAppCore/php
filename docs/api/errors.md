# API Errors

Core PHP Framework uses conventional HTTP response codes and provides detailed error information to help you debug issues.

## HTTP Status Codes

### 2xx Success

| Code | Status | Description |
|------|--------|-------------|
| 200 | OK | Request succeeded |
| 201 | Created | Resource created successfully |
| 202 | Accepted | Request accepted for processing |
| 204 | No Content | Request succeeded, no content to return |

### 4xx Client Errors

| Code | Status | Description |
|------|--------|-------------|
| 400 | Bad Request | Invalid request format or parameters |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Authenticated but not authorized |
| 404 | Not Found | Resource doesn't exist |
| 405 | Method Not Allowed | HTTP method not supported for endpoint |
| 409 | Conflict | Request conflicts with current state |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |

### 5xx Server Errors

| Code | Status | Description |
|------|--------|-------------|
| 500 | Internal Server Error | Unexpected server error |
| 502 | Bad Gateway | Invalid response from upstream server |
| 503 | Service Unavailable | Server temporarily unavailable |
| 504 | Gateway Timeout | Upstream server timeout |

## Error Response Format

All errors return JSON with consistent structure:

```json
{
  "message": "Human-readable error message",
  "error_code": "MACHINE_READABLE_CODE",
  "errors": {
    "field": ["Detailed validation errors"]
  },
  "meta": {
    "timestamp": "2026-01-26T12:00:00Z",
    "request_id": "req_abc123"
  }
}
```

## Common Errors

### 400 Bad Request

**Missing Required Parameter:**
```json
{
  "message": "Missing required parameter: title",
  "error_code": "MISSING_PARAMETER",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

**Invalid Parameter Type:**
```json
{
  "message": "Invalid parameter type",
  "error_code": "INVALID_TYPE",
  "errors": {
    "published_at": ["The published at must be a valid date."]
  }
}
```

### 401 Unauthorized

**Missing Authentication:**
```json
{
  "message": "Unauthenticated.",
  "error_code": "UNAUTHENTICATED"
}
```

**Invalid API Key:**
```json
{
  "message": "Invalid API key",
  "error_code": "INVALID_API_KEY"
}
```

**Expired Token:**
```json
{
  "message": "Token has expired",
  "error_code": "TOKEN_EXPIRED",
  "meta": {
    "expired_at": "2026-01-20T12:00:00Z"
  }
}
```

### 403 Forbidden

**Insufficient Permissions:**
```json
{
  "message": "This action is unauthorized.",
  "error_code": "INSUFFICIENT_PERMISSIONS",
  "required_scope": "posts:write",
  "provided_scopes": ["posts:read"]
}
```

**Workspace Suspended:**
```json
{
  "message": "Workspace is suspended",
  "error_code": "WORKSPACE_SUSPENDED",
  "meta": {
    "suspended_at": "2026-01-25T12:00:00Z",
    "reason": "Payment overdue"
  }
}
```

**Namespace Access Denied:**
```json
{
  "message": "You do not have access to this namespace",
  "error_code": "NAMESPACE_ACCESS_DENIED"
}
```

### 404 Not Found

**Resource Not Found:**
```json
{
  "message": "Post not found",
  "error_code": "RESOURCE_NOT_FOUND",
  "resource_type": "Post",
  "resource_id": 999
}
```

**Endpoint Not Found:**
```json
{
  "message": "Endpoint not found",
  "error_code": "ENDPOINT_NOT_FOUND",
  "requested_path": "/v1/nonexistent"
}
```

### 409 Conflict

**Duplicate Resource:**
```json
{
  "message": "A post with this slug already exists",
  "error_code": "DUPLICATE_RESOURCE",
  "conflicting_field": "slug",
  "existing_resource_id": 123
}
```

**State Conflict:**
```json
{
  "message": "Post is already published",
  "error_code": "STATE_CONFLICT",
  "current_state": "published",
  "requested_action": "publish"
}
```

### 422 Unprocessable Entity

**Validation Failed:**
```json
{
  "message": "The given data was invalid.",
  "error_code": "VALIDATION_FAILED",
  "errors": {
    "title": [
      "The title field is required."
    ],
    "content": [
      "The content must be at least 10 characters."
    ],
    "category_id": [
      "The selected category is invalid."
    ]
  }
}
```

### 429 Too Many Requests

**Rate Limit Exceeded:**
```json
{
  "message": "Too many requests",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "limit": 10000,
  "remaining": 0,
  "reset_at": "2026-01-26T13:00:00Z",
  "retry_after": 3600
}
```

**Usage Quota Exceeded:**
```json
{
  "message": "Monthly usage quota exceeded",
  "error_code": "QUOTA_EXCEEDED",
  "quota_type": "monthly",
  "limit": 50000,
  "used": 50000,
  "reset_at": "2026-02-01T00:00:00Z"
}
```

### 500 Internal Server Error

**Unexpected Error:**
```json
{
  "message": "An unexpected error occurred",
  "error_code": "INTERNAL_ERROR",
  "meta": {
    "request_id": "req_abc123",
    "timestamp": "2026-01-26T12:00:00Z"
  }
}
```

::: tip
In production, internal error messages are sanitized. Include the `request_id` when reporting issues for debugging.
:::

## Error Codes

### Authentication Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `UNAUTHENTICATED` | 401 | No authentication provided |
| `INVALID_API_KEY` | 401 | API key is invalid or revoked |
| `TOKEN_EXPIRED` | 401 | Authentication token has expired |
| `INVALID_CREDENTIALS` | 401 | Username/password incorrect |
| `INSUFFICIENT_PERMISSIONS` | 403 | Missing required permissions/scopes |

### Resource Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `RESOURCE_NOT_FOUND` | 404 | Requested resource doesn't exist |
| `DUPLICATE_RESOURCE` | 409 | Resource with identifier already exists |
| `RESOURCE_LOCKED` | 409 | Resource is locked by another process |
| `STATE_CONFLICT` | 409 | Action conflicts with current state |

### Validation Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_FAILED` | 422 | One or more fields failed validation |
| `INVALID_TYPE` | 400 | Parameter has wrong data type |
| `MISSING_PARAMETER` | 400 | Required parameter not provided |
| `INVALID_FORMAT` | 400 | Parameter format is invalid |

### Rate Limiting Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests in time window |
| `QUOTA_EXCEEDED` | 429 | Usage quota exceeded |
| `CONCURRENT_LIMIT_EXCEEDED` | 429 | Too many concurrent requests |

### Business Logic Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `ENTITLEMENT_DENIED` | 403 | Feature not included in plan |
| `WORKSPACE_SUSPENDED` | 403 | Workspace is suspended |
| `NAMESPACE_ACCESS_DENIED` | 403 | No access to namespace |
| `PAYMENT_REQUIRED` | 402 | Payment required to proceed |

### System Errors

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INTERNAL_ERROR` | 500 | Unexpected server error |
| `SERVICE_UNAVAILABLE` | 503 | Service temporarily unavailable |
| `GATEWAY_TIMEOUT` | 504 | Upstream service timeout |
| `MAINTENANCE_MODE` | 503 | System under maintenance |

## Handling Errors

### JavaScript Example

```javascript
async function createPost(data) {
  try {
    const response = await fetch('/api/v1/posts', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });

    if (!response.ok) {
      const error = await response.json();

      switch (response.status) {
        case 401:
          // Re-authenticate
          redirectToLogin();
          break;
        case 403:
          // Show permission error
          showError('You do not have permission to create posts');
          break;
        case 422:
          // Show validation errors
          showValidationErrors(error.errors);
          break;
        case 429:
          // Show rate limit message
          showError(`Rate limited. Retry after ${error.retry_after} seconds`);
          break;
        default:
          // Generic error
          showError(error.message);
      }

      return null;
    }

    return await response.json();
  } catch (err) {
    // Network error
    showError('Network error. Please check your connection.');
    return null;
  }
}
```

### PHP Example

```php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$client = new Client(['base_uri' => 'https://api.example.com']);

try {
    $response = $client->post('/v1/posts', [
        'headers' => [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ],
        'json' => $data,
    ]);

    $post = json_decode($response->getBody(), true);

} catch (RequestException $e) {
    $statusCode = $e->getResponse()->getStatusCode();
    $error = json_decode($e->getResponse()->getBody(), true);

    switch ($statusCode) {
        case 401:
            throw new AuthenticationException($error['message']);
        case 403:
            throw new AuthorizationException($error['message']);
        case 422:
            throw new ValidationException($error['errors']);
        case 429:
            throw new RateLimitException($error['retry_after']);
        default:
            throw new ApiException($error['message']);
    }
}
```

## Debugging

### Request ID

Every response includes a `request_id` for debugging:

```bash
curl -i https://api.example.com/v1/posts
```

Response headers:
```
X-Request-ID: req_abc123def456
```

Include this ID when reporting issues.

### Debug Mode

In development, enable debug mode for detailed errors:

```php
// .env
APP_DEBUG=true
```

Debug responses include:
- Full stack traces
- SQL queries
- Exception details

::: danger
Never enable debug mode in production! It exposes sensitive information.
:::

### Logging

All errors are logged with context:

```
[2026-01-26 12:00:00] production.ERROR: Post not found
{
  "user_id": 123,
  "workspace_id": 456,
  "namespace_id": 789,
  "post_id": 999,
  "request_id": "req_abc123"
}
```

## Best Practices

### 1. Always Check Status Codes

```javascript
// ✅ Good
if (!response.ok) {
  handleError(response);
}

// ❌ Bad - assumes success
const data = await response.json();
```

### 2. Handle All Error Types

```javascript
// ✅ Good - specific handling
switch (error.error_code) {
  case 'RATE_LIMIT_EXCEEDED':
    retryAfter(error.retry_after);
    break;
  case 'VALIDATION_FAILED':
    showValidationErrors(error.errors);
    break;
  default:
    showGenericError(error.message);
}

// ❌ Bad - generic handling
alert(error.message);
```

### 3. Implement Retry Logic

```javascript
async function fetchWithRetry(url, options, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(url, options);

      if (response.status === 429) {
        // Rate limited - wait and retry
        const retryAfter = parseInt(response.headers.get('Retry-After'));
        await sleep(retryAfter * 1000);
        continue;
      }

      return response;
    } catch (err) {
      if (i === retries - 1) throw err;
      await sleep(1000 * Math.pow(2, i)); // Exponential backoff
    }
  }
}
```

### 4. Log Error Context

```javascript
// ✅ Good - log context
console.error('API Error:', {
  endpoint: '/v1/posts',
  method: 'POST',
  status: response.status,
  error_code: error.error_code,
  request_id: error.meta.request_id
});

// ❌ Bad - no context
console.error(error.message);
```

## Learn More

- [API Authentication →](/api/authentication)
- [Rate Limiting →](/api/endpoints#rate-limiting)
- [API Endpoints →](/api/endpoints)
