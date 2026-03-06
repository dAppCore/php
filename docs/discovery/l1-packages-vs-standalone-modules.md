# Discovery: L1 Packages vs Standalone php-* Modules

**Issue:** #3
**Date:** 2026-02-21
**Status:** Complete – findings filed as issues #4, #5, #6, #7

## L1 Packages (Boot.php files under src/Core/)

| Package | Path | Has Standalone? |
|---------|------|----------------|
| Activity | `src/Core/Activity/` | No |
| Bouncer | `src/Core/Bouncer/` | No |
| Bouncer/Gate | `src/Core/Bouncer/Gate/` | No |
| Cdn | `src/Core/Cdn/` | No |
| Config | `src/Core/Config/` | No |
| Console | `src/Core/Console/` | No |
| Front | `src/Core/Front/` | No (root) |
| Front/Admin | `src/Core/Front/Admin/` | Partial – `core/php-admin` extends |
| Front/Api | `src/Core/Front/Api/` | Partial – `core/php-api` extends |
| Front/Cli | `src/Core/Front/Cli/` | No |
| Front/Client | `src/Core/Front/Client/` | No |
| Front/Components | `src/Core/Front/Components/` | No |
| Front/Mcp | `src/Core/Front/Mcp/` | Intentional – `core/php-mcp` fills |
| Front/Stdio | `src/Core/Front/Stdio/` | No |
| Front/Web | `src/Core/Front/Web/` | No |
| Headers | `src/Core/Headers/` | No |
| Helpers | `src/Core/Helpers/` | No |
| Lang | `src/Core/Lang/` | No |
| Mail | `src/Core/Mail/` | No |
| Media | `src/Core/Media/` | No |
| Search | `src/Core/Search/` | No (admin search is separate concern) |
| Seo | `src/Core/Seo/` | No |

## Standalone Repos

| Repo | Package | Namespace | Relationship |
|------|---------|-----------|-------------|
| `core/php-tenant` | `host-uk/core-tenant` | `Core\Tenant\` | Extension |
| `core/php-admin` | `host-uk/core-admin` | `Core\Admin\` | Extends Front/Admin |
| `core/php-api` | `host-uk/core-api` | `Core\Api\` | Extends Front/Api |
| `core/php-content` | `host-uk/core-content` | `Core\Mod\Content\` | Extension |
| `core/php-commerce` | `host-uk/core-commerce` | `Core\Mod\Commerce\` | Extension |
| `core/php-agentic` | `host-uk/core-agentic` | `Core\Mod\Agentic\` | Extension |
| `core/php-mcp` | `host-uk/core-mcp` | `Core\Mcp\` | Fills Front/Mcp shell |
| `core/php-developer` | `host-uk/core-developer` | `Core\Developer\` | Extension (also needs core-admin) |
| `core/php-devops` | *(DevOps tooling)* | N/A | Not a PHP module |

## Overlaps Found

See issues filed:

- **#4** `Front/Api` rate limiting vs `core/php-api` `RateLimitApi` middleware – double rate limiting risk
- **#5** `Core\Search` vs `core/php-admin` search subsystem – dual registries
- **#6** `Core\Activity` UI duplicated in `core/php-admin` and `core/php-developer`
- **#7** Summary issue with full analysis
