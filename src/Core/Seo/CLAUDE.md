# Core\Seo

SEO metadata management, JSON-LD schema generation, structured data validation, OG image handling, and score trend tracking.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Schema` | High-level JSON-LD generator: auto-detects Article, HowTo, FAQ, Breadcrumb from content |
| `SeoMetadata` | Eloquent model (polymorphic): title, description, canonical, OG, Twitter, schema markup, score, issues |
| `HasSeoMetadata` | Trait for models: `seoMetadata()` morphOne, `updateSeo()`, `getSeoHeadTagsAttribute()` |
| `Boot` | Service provider: registers singletons + artisan commands |

### Services/

| Class | Purpose |
|-------|---------|
| `SchemaBuilderService` | Lower-level schema building blocks |
| `ServiceOgImageService` | OG image generation for service pages |

### Validation/

| Class | Purpose |
|-------|---------|
| `SchemaValidator` | Validates schema against schema.org specifications |
| `StructuredDataTester` | Tests structured data, checks rich results eligibility, generates reports |
| `CanonicalUrlValidator` | Validates URL format and detects conflicts between records |
| `OgImageValidator` | Validates OG image dimensions and requirements |

### Analytics/

| Class | Purpose |
|-------|---------|
| `SeoScoreTrend` | Daily/weekly score trend tracking |
| `Models\SeoScoreHistory` | Historical score records |

## Schema Generation

`Schema::generateSchema($item)` builds a `@graph` containing:
1. Organisation schema (always, from config)
2. Article schema (TechArticle by default)
3. Breadcrumb schema
4. HowTo schema (if content has numbered steps)
5. FAQ schema (if content has FAQ section with Q&A pairs)

Content detection uses regex on `display_content`. Steps extracted from JSON blocks or numbered lists. FAQs from `## FAQ` sections.

## SeoMetadata Model

- **Lazy schema_markup**: Custom accessor/mutator defers JSON parsing until accessed
- **Meta tag generation**: `meta_tags` attribute produces complete HTML `<title>`, `<meta>`, `<link rel="canonical">`, OG, Twitter tags
- **JSON-LD output**: `json_ld` attribute wraps schema in `<script type="application/ld+json">` with XSS-safe `JSON_HEX_TAG`
- **Score tracking**: `recordScore()`, `getScoreHistory()`, `getDailyScoreTrend()`, `hasScoreImproved()`
- **Validation**: `validateOgImage()`, `validateCanonicalUrl()`, `checkCanonicalConflict()`, `validateStructuredData()`, `getRichResultsEligibility()`

## Console Commands

- `seo:record-scores` -- Record SEO scores for trend tracking
- `seo:test-structured-data` -- Test structured data against schema.org
- `seo:audit-canonical` -- Audit canonical URLs for conflicts
- `seo:generate-og-images` -- Generate OG images for services

## Configuration

Under `config/seo.php`:
- `trends.enabled`, `trends.retention_days`, `trends.record_on_save`, `trends.min_interval_hours`
- `structured_data.external_validation`, `structured_data.google_api_key`, `structured_data.cache_validation`

## Integration

- Use `HasSeoMetadata` trait on any Eloquent model to add polymorphic SEO data
- `Schema` reads organisation config from `core.organisation.*` and `core.social.*`
- Uses UK English in code: `colour`, `organisation`
