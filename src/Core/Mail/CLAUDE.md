# Core\Mail

Email validation and disposable domain blocking service with statistics tracking.

## Key Classes

| Class | Purpose |
|-------|---------|
| `EmailShield` | Main service: validate emails, block disposable domains, MX lookup, normalisation, async validation |
| `EmailShieldStat` | Eloquent model for daily validation stats (valid/invalid/disposable counts) |
| `EmailValidationResult` | Value object with named constructors: `valid()`, `invalid()`, `disposable()` |
| `Rules\ValidatedEmail` | Laravel validation rule wrapping EmailShield |
| `Boot` | Service provider: registers EmailShield singleton + backward-compat aliases |

## EmailShield Features

- **Disposable domain blocking**: 100k+ domains from community-maintained GitHub list, cached 24h, stored at `storage/app/email-shield/disposable-domains.txt`
- **MX record validation**: Cached 1h per domain, suppresses DNS warnings
- **Validation caching**: Results cached 5 min to avoid repeated checks
- **Email normalisation**: Gmail dot-stripping, plus-addressing removal, googlemail.com -> gmail.com
- **Async validation**: Immediate format+disposable check, queues MX lookup for background processing
- **Statistics**: Atomic daily counters via `insertOrIgnore` + `increment`

## Public API

```php
$shield = app(EmailShield::class);

// Validate
$result = $shield->validate('user@example.com');  // EmailValidationResult
$result->passes();  // bool
$result->isDisposable;  // bool

// Normalise
$shield->normalize('J.O.H.N+spam@gmail.com');  // 'john@gmail.com'
$shield->isSameMailbox('john@gmail.com', 'j.o.h.n@googlemail.com');  // true

// Update blocklist
$shield->updateDisposableDomainsList();

// In validation rules
'email' => ['required', new ValidatedEmail(blockDisposable: true)]
```

## Conventions

- Disposable domain list update validates minimum 100 domains to prevent corrupted lists.
- `EmailShieldStat::pruneOldRecords(90)` for cleanup (called by Console PruneEmailShieldStatsCommand).
- Backward-compat aliases map `App\Services\Email\*` to `Core\Mail\*`.
