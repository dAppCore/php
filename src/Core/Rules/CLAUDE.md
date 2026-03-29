# Core\Rules

Security-focused Laravel validation rules. No service provider -- use directly in validation arrays.

## Rules

### SafeWebhookUrl

SSRF protection for webhook delivery URLs.

**Blocks:**
- Localhost and loopback (127.0.0.0/8, ::1)
- Private networks (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- Link-local, reserved ranges, special-use addresses
- Local domain names (.local, .localhost, .internal)
- Decimal IP encoding (2130706433 = 127.0.0.1)
- IPv4-mapped IPv6 (::ffff:127.0.0.1)
- Non-HTTPS schemes

**Service mode:** Optionally restrict to known webhook domains (Discord, Slack, Telegram). Known service domains skip SSRF checks.

```php
'url' => [new SafeWebhookUrl]                    // any HTTPS, no SSRF
'url' => [new SafeWebhookUrl('discord')]          // discord.com/discordapp.com only
```

### SafeJsonPayload

Protects against malicious JSON payloads stored in the database.

**Validates:**
- Maximum total size (default 10 KB)
- Maximum nesting depth (default 3)
- Maximum total keys across all levels (default 50)
- Maximum string value length (default 1000 chars)

**Factory methods:**
- `SafeJsonPayload::default()` -- 10 KB, depth 3, 50 keys
- `SafeJsonPayload::small()` -- 2 KB, depth 2, 20 keys
- `SafeJsonPayload::large()` -- 100 KB, depth 5, 200 keys
- `SafeJsonPayload::metadata()` -- 5 KB, depth 2, 30 keys, 256 char strings

```php
'payload' => ['array', SafeJsonPayload::metadata()]
```

## Conventions

- Both rules implement `Illuminate\Contracts\Validation\ValidationRule`.
- `SafeWebhookUrl` resolves hostnames and checks ALL returned IPs against blocklists.
- These are standalone -- no Boot provider, no config. Import and use directly.
