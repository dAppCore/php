# Activity/Console/ — Activity Log Commands

## Commands

| Command | Signature | Purpose |
|---------|-----------|---------|
| `ActivityPruneCommand` | `activity:prune` | Prunes old activity logs. Options: `--days=N` (retention period), `--dry-run` (show count without deleting). Uses retention from config when days not specified. |

Part of the Activity subsystem's maintenance tooling. Should be scheduled in the application's console kernel for regular cleanup.
