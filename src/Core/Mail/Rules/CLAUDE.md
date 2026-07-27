# Mail/Rules/ — Email Validation Rules

## Rules

| Rule | Purpose |
|------|---------|
| `ValidatedEmail` | Laravel validation rule using the `EmailShield` service. Validates email format and optionally blocks disposable email domains. Constructor takes `blockDisposable: true` (default). |

Usage:
```php
'email' => ['required', new ValidatedEmail(blockDisposable: true)]
```

Part of the Mail subsystem's input validation layer.
