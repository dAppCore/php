<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\TranslationMemory;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Translation Memory Entry.
 *
 * Represents a single translation unit in the translation memory.
 * Contains the source text, target text, metadata, and quality score.
 *
 * Quality scores range from 0.0 to 1.0:
 * - 1.0 = Human verified / perfect translation
 * - 0.9 = Machine translation reviewed
 * - 0.8 = High confidence automatic match
 * - 0.5-0.7 = Fuzzy match suggestions
 * - <0.5 = Low confidence, needs review
 *
 * @implements Arrayable<string, mixed>
 */
class TranslationMemoryEntry implements Arrayable, JsonSerializable
{
    /**
     * Create a new translation memory entry.
     *
     * @param  string  $id  Unique identifier for this entry
     * @param  string  $sourceLocale  Source language locale (e.g., 'en_GB')
     * @param  string  $targetLocale  Target language locale (e.g., 'de_DE')
     * @param  string  $source  Source text to translate
     * @param  string  $target  Translated text
     * @param  float  $quality  Quality/confidence score (0.0-1.0)
     * @param  DateTimeImmutable|null  $createdAt  When the entry was created
     * @param  DateTimeImmutable|null  $updatedAt  When the entry was last updated
     * @param  array<string, mixed>  $metadata  Additional metadata (context, domain, etc.)
     * @param  int  $usageCount  How many times this translation has been used
     */
    public function __construct(
        protected string $id,
        protected string $sourceLocale,
        protected string $targetLocale,
        protected string $source,
        protected string $target,
        protected float $quality = 1.0,
        protected ?DateTimeImmutable $createdAt = null,
        protected ?DateTimeImmutable $updatedAt = null,
        protected array $metadata = [],
        protected int $usageCount = 0,
    ) {
        $this->createdAt ??= new DateTimeImmutable;
        $this->updatedAt ??= new DateTimeImmutable;
        $this->quality = max(0.0, min(1.0, $quality));
    }

    /**
     * Create an entry from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'] ?? static::generateId($data['source'] ?? '', $data['source_locale'] ?? '', $data['target_locale'] ?? ''),
            sourceLocale: $data['source_locale'] ?? 'en',
            targetLocale: $data['target_locale'] ?? 'en',
            source: $data['source'] ?? '',
            target: $data['target'] ?? '',
            quality: (float) ($data['quality'] ?? 1.0),
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
            metadata: $data['metadata'] ?? [],
            usageCount: (int) ($data['usage_count'] ?? 0),
        );
    }

    /**
     * Generate a unique ID for a translation entry.
     */
    public static function generateId(string $source, string $sourceLocale, string $targetLocale): string
    {
        return hash('xxh128', "{$sourceLocale}:{$targetLocale}:{$source}");
    }

    /**
     * Get the entry ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the source locale.
     */
    public function getSourceLocale(): string
    {
        return $this->sourceLocale;
    }

    /**
     * Get the target locale.
     */
    public function getTargetLocale(): string
    {
        return $this->targetLocale;
    }

    /**
     * Get the source text.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get the target (translated) text.
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Get the quality score.
     */
    public function getQuality(): float
    {
        return $this->quality;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get the last update timestamp.
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the usage count.
     */
    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    /**
     * Create a new instance with updated target text.
     */
    public function withTarget(string $target): static
    {
        return new static(
            id: $this->id,
            sourceLocale: $this->sourceLocale,
            targetLocale: $this->targetLocale,
            source: $this->source,
            target: $target,
            quality: $this->quality,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            metadata: $this->metadata,
            usageCount: $this->usageCount,
        );
    }

    /**
     * Create a new instance with updated quality score.
     */
    public function withQuality(float $quality): static
    {
        return new static(
            id: $this->id,
            sourceLocale: $this->sourceLocale,
            targetLocale: $this->targetLocale,
            source: $this->source,
            target: $this->target,
            quality: $quality,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            metadata: $this->metadata,
            usageCount: $this->usageCount,
        );
    }

    /**
     * Create a new instance with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            id: $this->id,
            sourceLocale: $this->sourceLocale,
            targetLocale: $this->targetLocale,
            source: $this->source,
            target: $this->target,
            quality: $this->quality,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
            metadata: array_merge($this->metadata, $metadata),
            usageCount: $this->usageCount,
        );
    }

    /**
     * Create a new instance with incremented usage count.
     */
    public function withIncrementedUsage(): static
    {
        return new static(
            id: $this->id,
            sourceLocale: $this->sourceLocale,
            targetLocale: $this->targetLocale,
            source: $this->source,
            target: $this->target,
            quality: $this->quality,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            metadata: $this->metadata,
            usageCount: $this->usageCount + 1,
        );
    }

    /**
     * Check if this is an exact match for the given source.
     */
    public function isExactMatch(string $source): bool
    {
        return $this->source === $source;
    }

    /**
     * Check if this entry needs review (low quality).
     */
    public function needsReview(): bool
    {
        return $this->quality < 0.8;
    }

    /**
     * Check if this is a high-quality translation.
     */
    public function isHighQuality(): bool
    {
        return $this->quality >= 0.9;
    }

    /**
     * Get the locale pair as a string (source->target).
     */
    public function getLocalePair(): string
    {
        return "{$this->sourceLocale}->{$this->targetLocale}";
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_locale' => $this->sourceLocale,
            'target_locale' => $this->targetLocale,
            'source' => $this->source,
            'target' => $this->target,
            'quality' => $this->quality,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
            'metadata' => $this->metadata,
            'usage_count' => $this->usageCount,
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
