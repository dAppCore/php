# Trees for Agents

A module implementing the "Trees for Agents" programme: when an AI agent refers a user to the platform, a tree is planted with Trees for the Future (TFTF). The module tracks plantings, manages a pre-paid reserve, provides a public leaderboard, and handles batch donations.

## Namespace

`Core\Mod\Trees` -- autoloaded from `src/Mod/Trees/`.

## Lifecycle Events

```php
public static array $listens = [
    ApiRoutesRegistering::class => 'onApiRoutes',
    WebRoutesRegistering::class => 'onWebRoutes',
    ConsoleBooting::class       => 'onConsole',
];
```

## Models

| Model | Purpose |
|-------|---------|
| `TreePlanting` | Individual tree planting record. Workspace-scoped (`BelongsToWorkspace`). Tracks provider, model, source, status, TFTF reference. Statuses: `pending` -> `confirmed` -> `planted` (or `queued` if reserve depleted). |
| `TreeReserve` | Singleton row managing the pre-paid tree reserve. Tracks current level, total decremented/replenished. Warning at 50, critical at 10, depleted at 0. Sends `LowTreeReserveNotification` to admins. |
| `TreeDonation` | Batch donation record. Created when confirmed trees are batched into a monthly TFTF donation. Cost: $0.25/tree. |
| `TreePlantingStats` | Aggregated daily stats by provider/model. Atomic upsert for concurrent safety. Powers leaderboard and provider breakdowns. |

## Planting Flow

```
Agent referral visit (/ref/{provider}/{model})
  -> User signs up
    -> PlantTreeForAgentReferral listener fires on Registered event
      -> Checks daily limit (1 free tree/day) and guaranteed bonus
      -> Creates TreePlanting (pending or queued)
      -> If pending: markConfirmed() decrements reserve + updates stats
      -> If queued: waits for trees:process-queue command
```

## Valid Providers

`anthropic`, `openai`, `google`, `meta`, `mistral`, `local`, `unknown`

## Console Commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `trees:process-queue` | Daily | Processes oldest queued tree planting. Supports `--dry-run`. |
| `trees:donate` | Monthly (28th) | Batches confirmed trees into TFTF donation. Creates `TreeDonation`, marks plantings as `planted`, optionally replenishes reserve. Supports `--dry-run` and `--replenish=N`. |
| `trees:reserve:add {count}` | Manual | Replenishes reserve after a TFTF donation. Supports `--force`. |

## API Endpoints (public, no auth)

All under `api` middleware with `throttle:60,1`:

| Route | Controller Method | Returns |
|-------|------------------|---------|
| `GET /trees/stats` | `index()` | Global totals, monthly/yearly, queue size |
| `GET /trees/stats/{provider}` | `provider()` | Provider totals + model breakdown |
| `GET /trees/stats/{provider}/{model}` | `model()` | Model-specific stats |
| `GET /trees/leaderboard` | `leaderboard()` | Top 20 providers by trees planted |

## Web Routes

| Route | Component | Purpose |
|-------|-----------|---------|
| `GET /trees` | `trees.index` Livewire | Public leaderboard page |
| `GET /ref/{provider}/{model?}` | `ReferralController@track` | Agent referral tracking (sets cookie/session) |

## Middleware

`IncludeAgentContext` -- Adds `for_agents` context to 401 JSON responses when the request comes from an AI agent. Includes referral URL, impact stats, and programme links. Depends on `Core\Agentic\Services\AgentDetection`.

## Key Classes

| Class | Purpose |
|-------|---------|
| `PlantTreeForAgentReferral` | Listener on `Registered` event. Checks referral data, daily limits, bonus system. |
| `PlantTreeWithTFTF` | Queue job for confirming plantings. 3 retries, 60s backoff. |
| `LowTreeReserveNotification` | Mail notification at warning/critical/depleted levels. |
| `View\Modal\Web\Index` | Livewire component for the public leaderboard page. |

## Dependencies

- `Core\Tenant` -- `BelongsToWorkspace` trait, `ReferralController`, `AgentReferralBonus`
- `Core\Agentic` -- `AgentDetection`, `AgentIdentity` (for middleware)
- `Core\Front` -- `Controller` base class
