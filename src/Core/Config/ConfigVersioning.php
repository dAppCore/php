<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigVersion;
use Illuminate\Support\Collection;

/**
 * Configuration versioning service.
 *
 * Provides ability to version config changes and rollback to previous versions.
 * Each version captures a snapshot of all config values for a scope.
 *
 * ## Features
 *
 * - Create named snapshots of config state
 * - Rollback to any previous version
 * - Compare versions to see differences
 * - Automatic versioning on significant changes
 * - Retention policy for old versions
 *
 * ## Usage
 *
 * ```php
 * $versioning = app(ConfigVersioning::class);
 *
 * // Create a snapshot before changes
 * $version = $versioning->createVersion($workspace, 'Before CDN migration');
 *
 * // Make changes...
 * $config->set('cdn.provider', 'bunny', $profile);
 *
 * // Rollback if needed
 * $versioning->rollback($version->id, $workspace);
 *
 * // Compare versions
 * $diff = $versioning->compare($workspace, $oldVersionId, $newVersionId);
 * ```
 *
 * ## Version Structure
 *
 * Each version stores:
 * - Scope (workspace/system)
 * - Timestamp
 * - Label/description
 * - Full snapshot of all config values
 * - Author (if available)
 *
 * @see ConfigService For runtime config access
 * @see ConfigExporter For import/export operations
 */
class ConfigVersioning
{
    /**
     * Maximum versions to keep per scope (configurable).
     */
    protected int $maxVersions;

    public function __construct(
        protected ConfigService $config,
        protected ConfigExporter $exporter,
    ) {
        $this->maxVersions = (int) config('core.config.max_versions', 50);
    }

    /**
     * Create a new config version (snapshot).
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  string  $label  Version label/description
     * @param  string|null  $author  Author identifier (user ID, email, etc.)
     * @return ConfigVersion The created version
     */
    public function createVersion(
        ?object $workspace = null,
        string $label = '',
        ?string $author = null,
    ): ConfigVersion {
        $profile = $this->getOrCreateProfile($workspace);

        // Get current config as JSON snapshot
        $snapshot = $this->exporter->exportJson($workspace, includeSensitive: true, includeKeys: false);

        $version = ConfigVersion::create([
            'profile_id' => $profile->id,
            'workspace_id' => $workspace?->id,
            'label' => $label ?: 'Version '.now()->format('Y-m-d H:i:s'),
            'snapshot' => $snapshot,
            'author' => $author ?? $this->getCurrentAuthor(),
            'created_at' => now(),
        ]);

        // Enforce retention policy
        $this->pruneOldVersions($profile->id);

        return $version;
    }

    /**
     * Rollback to a specific version.
     *
     * @param  int  $versionId  Version ID to rollback to
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $createBackup  Create a backup version before rollback (default: true)
     * @return ImportResult Import result with stats
     *
     * @throws \InvalidArgumentException If version not found or scope mismatch
     */
    public function rollback(
        int $versionId,
        ?object $workspace = null,
        bool $createBackup = true,
    ): ImportResult {
        $version = ConfigVersion::find($versionId);

        if ($version === null) {
            throw new \InvalidArgumentException("Version not found: {$versionId}");
        }

        // Verify scope matches
        $workspaceId = $workspace?->id;
        if ($version->workspace_id !== $workspaceId) {
            throw new \InvalidArgumentException('Version scope does not match target scope');
        }

        // Create backup before rollback
        if ($createBackup) {
            $this->createVersion($workspace, 'Backup before rollback to version '.$versionId);
        }

        // Import the snapshot
        return $this->exporter->importJson($version->snapshot, $workspace);
    }

    /**
     * Get all versions for a scope.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  int  $limit  Maximum versions to return
     * @return Collection<int, ConfigVersion>
     */
    public function getVersions(?object $workspace = null, int $limit = 20): Collection
    {
        $workspaceId = $workspace?->id;

        return ConfigVersion::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a specific version.
     *
     * @param  int  $versionId  Version ID
     */
    public function getVersion(int $versionId): ?ConfigVersion
    {
        return ConfigVersion::find($versionId);
    }

    /**
     * Compare two versions.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  int  $oldVersionId  Older version ID
     * @param  int  $newVersionId  Newer version ID
     * @return VersionDiff Difference between versions
     *
     * @throws \InvalidArgumentException If versions not found
     */
    public function compare(?object $workspace, int $oldVersionId, int $newVersionId): VersionDiff
    {
        $oldVersion = ConfigVersion::find($oldVersionId);
        $newVersion = ConfigVersion::find($newVersionId);

        if ($oldVersion === null) {
            throw new \InvalidArgumentException("Old version not found: {$oldVersionId}");
        }

        if ($newVersion === null) {
            throw new \InvalidArgumentException("New version not found: {$newVersionId}");
        }

        // Parse snapshots
        $oldData = json_decode($oldVersion->snapshot, true)['values'] ?? [];
        $newData = json_decode($newVersion->snapshot, true)['values'] ?? [];

        return $this->computeDiff($oldData, $newData);
    }

    /**
     * Compare current state with a version.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  int  $versionId  Version ID to compare against
     * @return VersionDiff Difference between version and current state
     *
     * @throws \InvalidArgumentException If version not found
     */
    public function compareWithCurrent(?object $workspace, int $versionId): VersionDiff
    {
        $version = ConfigVersion::find($versionId);

        if ($version === null) {
            throw new \InvalidArgumentException("Version not found: {$versionId}");
        }

        // Get current state
        $currentJson = $this->exporter->exportJson($workspace, includeSensitive: true, includeKeys: false);
        $currentData = json_decode($currentJson, true)['values'] ?? [];

        // Get version state
        $versionData = json_decode($version->snapshot, true)['values'] ?? [];

        return $this->computeDiff($versionData, $currentData);
    }

    /**
     * Compute difference between two value arrays.
     *
     * @param  array<array{key: string, value: mixed, locked: bool}>  $oldValues
     * @param  array<array{key: string, value: mixed, locked: bool}>  $newValues
     */
    protected function computeDiff(array $oldValues, array $newValues): VersionDiff
    {
        $diff = new VersionDiff;

        // Index by key
        $oldByKey = collect($oldValues)->keyBy('key');
        $newByKey = collect($newValues)->keyBy('key');

        // Find added keys (in new but not in old)
        foreach ($newByKey as $key => $newValue) {
            if (! $oldByKey->has($key)) {
                $diff->addAdded($key, $newValue['value']);
            }
        }

        // Find removed keys (in old but not in new)
        foreach ($oldByKey as $key => $oldValue) {
            if (! $newByKey->has($key)) {
                $diff->addRemoved($key, $oldValue['value']);
            }
        }

        // Find changed keys (in both but different)
        foreach ($oldByKey as $key => $oldValue) {
            if ($newByKey->has($key)) {
                $newValue = $newByKey[$key];
                if ($oldValue['value'] !== $newValue['value']) {
                    $diff->addChanged($key, $oldValue['value'], $newValue['value']);
                }
                if (($oldValue['locked'] ?? false) !== ($newValue['locked'] ?? false)) {
                    $diff->addLockChanged($key, $oldValue['locked'] ?? false, $newValue['locked'] ?? false);
                }
            }
        }

        return $diff;
    }

    /**
     * Delete a version.
     *
     * @param  int  $versionId  Version ID
     *
     * @throws \InvalidArgumentException If version not found
     */
    public function deleteVersion(int $versionId): void
    {
        $version = ConfigVersion::find($versionId);

        if ($version === null) {
            throw new \InvalidArgumentException("Version not found: {$versionId}");
        }

        $version->delete();
    }

    /**
     * Prune old versions beyond retention limit.
     *
     * @param  int  $profileId  Profile ID
     */
    protected function pruneOldVersions(int $profileId): void
    {
        $versions = ConfigVersion::where('profile_id', $profileId)
            ->orderByDesc('created_at')
            ->get();

        if ($versions->count() > $this->maxVersions) {
            $toDelete = $versions->slice($this->maxVersions);
            foreach ($toDelete as $version) {
                $version->delete();
            }
        }
    }

    /**
     * Get or create profile for a workspace (or system).
     */
    protected function getOrCreateProfile(?object $workspace): ConfigProfile
    {
        if ($workspace !== null) {
            return ConfigProfile::ensureWorkspace($workspace->id);
        }

        return ConfigProfile::ensureSystem();
    }

    /**
     * Get current author for version attribution.
     */
    protected function getCurrentAuthor(): ?string
    {
        // Try to get authenticated user
        if (function_exists('auth') && auth()->check()) {
            $user = auth()->user();

            return $user->email ?? $user->name ?? (string) $user->id;
        }

        // Return null if no user context
        return null;
    }

    /**
     * Set maximum versions to keep per scope.
     *
     * @param  int  $max  Maximum versions
     */
    public function setMaxVersions(int $max): void
    {
        $this->maxVersions = max(1, $max);
    }

    /**
     * Get maximum versions to keep per scope.
     */
    public function getMaxVersions(): int
    {
        return $this->maxVersions;
    }
}
