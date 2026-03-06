<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo;

use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeoMetadata
{
    /**
     * Get the SEO metadata for this model.
     */
    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    /**
     * Get the SEO metadata (alias for convenience).
     */
    public function getSeoAttribute(): ?SeoMetadata
    {
        return $this->seoMetadata;
    }

    /**
     * Update or create SEO metadata.
     */
    public function updateSeo(array $data): SeoMetadata
    {
        return $this->seoMetadata()->updateOrCreate([], $data);
    }

    /**
     * Check if this model has SEO metadata.
     */
    public function hasSeo(): bool
    {
        return $this->seoMetadata()->exists();
    }

    /**
     * Get the SEO title, falling back to the model's title.
     */
    public function getSeoTitleAttribute(): string
    {
        return $this->seoMetadata?->title ?? $this->title ?? '';
    }

    /**
     * Get the SEO description, falling back to excerpt if available.
     */
    public function getSeoDescriptionAttribute(): string
    {
        return $this->seoMetadata?->description ?? $this->excerpt ?? '';
    }

    /**
     * Generate complete head tags for this model.
     */
    public function getSeoHeadTagsAttribute(): string
    {
        if (! $this->seoMetadata) {
            return '';
        }

        $tags = $this->seoMetadata->meta_tags;

        if ($jsonLd = $this->seoMetadata->json_ld) {
            $tags .= "\n    ".$jsonLd;
        }

        return $tags;
    }
}
