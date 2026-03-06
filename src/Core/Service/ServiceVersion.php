<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service;

/**
 * Represents a service version with deprecation information.
 *
 * Follows semantic versioning (major.minor.patch) with support
 * for deprecation notices and sunset dates.
 *
 * ## Semantic Versioning
 *
 * Versions follow [SemVer](https://semver.org/) conventions:
 *
 * - **Major**: Breaking changes to the service contract
 * - **Minor**: New features, backwards compatible
 * - **Patch**: Bug fixes, backwards compatible
 *
 * ## Lifecycle States
 *
 * ```
 * [Active] ──deprecate()──> [Deprecated] ──isPastSunset()──> [Sunset]
 * ```
 *
 * - **Active**: Service is fully supported
 * - **Deprecated**: Service works but consumers should migrate
 * - **Sunset**: Service should no longer be used (past sunset date)
 *
 * ## Usage Examples
 *
 * ```php
 * // Create a version
 * $version = new ServiceVersion(2, 1, 0);
 * echo $version; // "2.1.0"
 *
 * // Parse from string
 * $version = ServiceVersion::fromString('v2.1.0');
 *
 * // Mark as deprecated with sunset date
 * $version = (new ServiceVersion(1, 0, 0))
 *     ->deprecate(
 *         'Migrate to v2.x - see docs/migration.md',
 *         new \DateTimeImmutable('2025-06-01')
 *     );
 *
 * // Check compatibility
 * $minimum = new ServiceVersion(1, 5, 0);
 * $current = new ServiceVersion(1, 8, 2);
 * $current->isCompatibleWith($minimum); // true (same major, >= minor)
 *
 * // Check if past sunset
 * if ($version->isPastSunset()) {
 *     throw new ServiceSunsetException('Service no longer available');
 * }
 * ```
 */
final readonly class ServiceVersion
{
    public function __construct(
        public int $major,
        public int $minor = 0,
        public int $patch = 0,
        public bool $deprecated = false,
        public ?string $deprecationMessage = null,
        public ?\DateTimeInterface $sunsetDate = null,
    ) {}

    /**
     * Create a version from a string (e.g., "1.2.3").
     */
    public static function fromString(string $version): self
    {
        $parts = explode('.', ltrim($version, 'v'));

        return new self(
            major: (int) ($parts[0] ?? 1),
            minor: (int) ($parts[1] ?? 0),
            patch: (int) ($parts[2] ?? 0),
        );
    }

    /**
     * Create a version 1.0.0 instance.
     */
    public static function initial(): self
    {
        return new self(1, 0, 0);
    }

    /**
     * Mark this version as deprecated.
     */
    public function deprecate(string $message, ?\DateTimeInterface $sunsetDate = null): self
    {
        return new self(
            major: $this->major,
            minor: $this->minor,
            patch: $this->patch,
            deprecated: true,
            deprecationMessage: $message,
            sunsetDate: $sunsetDate,
        );
    }

    /**
     * Check if the version is past its sunset date.
     */
    public function isPastSunset(): bool
    {
        if ($this->sunsetDate === null) {
            return false;
        }

        return $this->sunsetDate < new \DateTimeImmutable;
    }

    /**
     * Compare with another version.
     *
     * @return int -1 if less, 0 if equal, 1 if greater
     */
    public function compare(self $other): int
    {
        if ($this->major !== $other->major) {
            return $this->major <=> $other->major;
        }

        if ($this->minor !== $other->minor) {
            return $this->minor <=> $other->minor;
        }

        return $this->patch <=> $other->patch;
    }

    /**
     * Check if this version is compatible with a minimum version.
     * Compatible if same major version and >= minor.patch.
     */
    public function isCompatibleWith(self $minimum): bool
    {
        if ($this->major !== $minimum->major) {
            return false;
        }

        return $this->compare($minimum) >= 0;
    }

    /**
     * Get version as string.
     */
    public function toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'version' => $this->toString(),
            'deprecated' => $this->deprecated ?: null,
            'deprecation_message' => $this->deprecationMessage,
            'sunset_date' => $this->sunsetDate?->format('Y-m-d'),
        ], fn ($v) => $v !== null);
    }
}
