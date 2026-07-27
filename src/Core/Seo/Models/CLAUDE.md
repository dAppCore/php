# Seo/Models/ — SEO Eloquent Models

## Models

| Model | Purpose |
|-------|---------|
| `SeoScoreHistory` | Historical SEO score records. Polymorphic (`seoable_type`/`seoable_id`) for any model with SEO metadata. Stores point-in-time snapshots of scores and issues. Supports daily and weekly aggregation for trend analysis. |
