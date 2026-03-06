<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Core\Config\Enums\ConfigType;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigValue;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration import/export service.
 *
 * Provides functionality to export config to JSON/YAML and import back.
 * Supports workspace-level and system-level config export/import.
 *
 * ## Export Formats
 *
 * - JSON: Standard JSON format with metadata
 * - YAML: Human-readable YAML format for manual editing
 *
 * ## Export Structure
 *
 * ```json
 * {
 *   "version": "1.0",
 *   "exported_at": "2025-01-26T10:00:00Z",
 *   "scope": {
 *     "type": "workspace",
 *     "id": 123
 *   },
 *   "keys": [
 *     {
 *       "code": "cdn.bunny.api_key",
 *       "type": "string",
 *       "category": "cdn",
 *       "description": "BunnyCDN API key",
 *       "is_sensitive": true
 *     }
 *   ],
 *   "values": [
 *     {
 *       "key": "cdn.bunny.api_key",
 *       "value": "***SENSITIVE***",
 *       "locked": false
 *     }
 *   ]
 * }
 * ```
 *
 * ## Usage
 *
 * ```php
 * $exporter = app(ConfigExporter::class);
 *
 * // Export to JSON
 * $json = $exporter->exportJson($workspace);
 * file_put_contents('config.json', $json);
 *
 * // Export to YAML
 * $yaml = $exporter->exportYaml($workspace);
 * file_put_contents('config.yaml', $yaml);
 *
 * // Import from JSON
 * $result = $exporter->importJson(file_get_contents('config.json'), $workspace);
 *
 * // Import from YAML
 * $result = $exporter->importYaml(file_get_contents('config.yaml'), $workspace);
 * ```
 *
 * @see ConfigService For runtime config access
 * @see ConfigVersioning For config versioning and rollback
 */
class ConfigExporter
{
    /**
     * Current export format version.
     */
    protected const FORMAT_VERSION = '1.0';

    /**
     * Placeholder for sensitive values in exports.
     */
    protected const SENSITIVE_PLACEHOLDER = '***SENSITIVE***';

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * Export config to JSON format.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $includeSensitive  Include sensitive values (default: false)
     * @param  bool  $includeKeys  Include key definitions (default: true)
     * @param  string|null  $category  Filter by category (optional)
     * @return string JSON string
     */
    public function exportJson(
        ?object $workspace = null,
        bool $includeSensitive = false,
        bool $includeKeys = true,
        ?string $category = null,
    ): string {
        $data = $this->buildExportData($workspace, $includeSensitive, $includeKeys, $category);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export config to YAML format.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $includeSensitive  Include sensitive values (default: false)
     * @param  bool  $includeKeys  Include key definitions (default: true)
     * @param  string|null  $category  Filter by category (optional)
     * @return string YAML string
     */
    public function exportYaml(
        ?object $workspace = null,
        bool $includeSensitive = false,
        bool $includeKeys = true,
        ?string $category = null,
    ): string {
        $data = $this->buildExportData($workspace, $includeSensitive, $includeKeys, $category);

        return Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * Build export data structure.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    protected function buildExportData(
        ?object $workspace,
        bool $includeSensitive,
        bool $includeKeys,
        ?string $category,
    ): array {
        $data = [
            'version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'scope' => [
                'type' => $workspace ? 'workspace' : 'system',
                'id' => $workspace?->id,
            ],
        ];

        // Get profile for this scope
        $profile = $this->getProfile($workspace);

        if ($includeKeys) {
            $data['keys'] = $this->exportKeys($category);
        }

        $data['values'] = $this->exportValues($profile, $includeSensitive, $category);

        return $data;
    }

    /**
     * Export key definitions.
     *
     * @return array<array<string, mixed>>
     */
    protected function exportKeys(?string $category = null): array
    {
        $query = ConfigKey::query()->orderBy('category')->orderBy('code');

        if ($category !== null) {
            $escapedCategory = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $category);
            $query->where('code', 'LIKE', "{$escapedCategory}.%")
                ->orWhere('category', $category);
        }

        return $query->get()->map(function (ConfigKey $key) {
            return [
                'code' => $key->code,
                'type' => $key->type->value,
                'category' => $key->category,
                'description' => $key->description,
                'default_value' => $key->default_value,
                'is_sensitive' => $key->is_sensitive ?? false,
            ];
        })->toArray();
    }

    /**
     * Export config values.
     *
     * @return array<array<string, mixed>>
     */
    protected function exportValues(?ConfigProfile $profile, bool $includeSensitive, ?string $category): array
    {
        if ($profile === null) {
            return [];
        }

        $query = ConfigValue::query()
            ->with('key')
            ->where('profile_id', $profile->id);

        $values = $query->get();

        return $values
            ->filter(function (ConfigValue $value) use ($category) {
                if ($category === null) {
                    return true;
                }
                $key = $value->key;
                if ($key === null) {
                    return false;
                }

                return str_starts_with($key->code, "{$category}.") || $key->category === $category;
            })
            ->map(function (ConfigValue $value) use ($includeSensitive) {
                $key = $value->key;

                // Mask sensitive values unless explicitly included
                $displayValue = $value->value;
                if ($key?->isSensitive() && ! $includeSensitive) {
                    $displayValue = self::SENSITIVE_PLACEHOLDER;
                }

                return [
                    'key' => $key?->code ?? 'unknown',
                    'value' => $displayValue,
                    'locked' => $value->locked,
                    'channel_id' => $value->channel_id,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Import config from JSON format.
     *
     * @param  string  $json  JSON string
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $dryRun  Preview changes without applying
     * @return ImportResult Import result with stats
     *
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public function importJson(string $json, ?object $workspace = null, bool $dryRun = false): ImportResult
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        return $this->importData($data, $workspace, $dryRun);
    }

    /**
     * Import config from YAML format.
     *
     * @param  string  $yaml  YAML string
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $dryRun  Preview changes without applying
     * @return ImportResult Import result with stats
     *
     * @throws \InvalidArgumentException If YAML is invalid
     */
    public function importYaml(string $yaml, ?object $workspace = null, bool $dryRun = false): ImportResult
    {
        try {
            $data = Yaml::parse($yaml);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid YAML: '.$e->getMessage());
        }

        return $this->importData($data, $workspace, $dryRun);
    }

    /**
     * Import config from parsed data.
     *
     * @param  array  $data  Parsed import data
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $dryRun  Preview changes without applying
     */
    protected function importData(array $data, ?object $workspace, bool $dryRun): ImportResult
    {
        $result = new ImportResult;

        // Validate version
        $version = $data['version'] ?? '1.0';
        if (! $this->isVersionCompatible($version)) {
            $result->addError("Incompatible export version: {$version} (expected {FORMAT_VERSION})");

            return $result;
        }

        // Get or create profile for this scope
        $profile = $this->getOrCreateProfile($workspace);

        // Import keys if present
        if (isset($data['keys']) && is_array($data['keys'])) {
            $this->importKeys($data['keys'], $result, $dryRun);
        }

        // Import values if present
        if (isset($data['values']) && is_array($data['values'])) {
            $this->importValues($data['values'], $profile, $result, $dryRun);
        }

        // Re-prime config if changes were made
        if (! $dryRun && $result->hasChanges()) {
            $this->config->prime($workspace);
        }

        return $result;
    }

    /**
     * Import key definitions.
     *
     * @param  array<array<string, mixed>>  $keys
     */
    protected function importKeys(array $keys, ImportResult $result, bool $dryRun): void
    {
        foreach ($keys as $keyData) {
            $code = $keyData['code'] ?? null;
            if ($code === null) {
                $result->addSkipped('Key with no code');

                continue;
            }

            try {
                $type = ConfigType::tryFrom($keyData['type'] ?? 'string') ?? ConfigType::STRING;

                $existing = ConfigKey::byCode($code);

                if ($existing !== null) {
                    // Update existing key
                    if (! $dryRun) {
                        $existing->update([
                            'type' => $type,
                            'category' => $keyData['category'] ?? $existing->category,
                            'description' => $keyData['description'] ?? $existing->description,
                            'default_value' => $keyData['default_value'] ?? $existing->default_value,
                            'is_sensitive' => $keyData['is_sensitive'] ?? $existing->is_sensitive,
                        ]);
                    }
                    $result->addUpdated($code, 'key');
                } else {
                    // Create new key
                    if (! $dryRun) {
                        ConfigKey::create([
                            'code' => $code,
                            'type' => $type,
                            'category' => $keyData['category'] ?? 'imported',
                            'description' => $keyData['description'] ?? null,
                            'default_value' => $keyData['default_value'] ?? null,
                            'is_sensitive' => $keyData['is_sensitive'] ?? false,
                        ]);
                    }
                    $result->addCreated($code, 'key');
                }
            } catch (\Exception $e) {
                $result->addError("Failed to import key '{$code}': ".$e->getMessage());
            }
        }
    }

    /**
     * Import config values.
     *
     * @param  array<array<string, mixed>>  $values
     */
    protected function importValues(array $values, ConfigProfile $profile, ImportResult $result, bool $dryRun): void
    {
        foreach ($values as $valueData) {
            $keyCode = $valueData['key'] ?? null;
            if ($keyCode === null) {
                $result->addSkipped('Value with no key');

                continue;
            }

            // Skip sensitive placeholders
            if ($valueData['value'] === self::SENSITIVE_PLACEHOLDER) {
                $result->addSkipped("{$keyCode} (sensitive placeholder)");

                continue;
            }

            try {
                $key = ConfigKey::byCode($keyCode);
                if ($key === null) {
                    $result->addSkipped("{$keyCode} (key not found)");

                    continue;
                }

                $channelId = $valueData['channel_id'] ?? null;
                $existing = ConfigValue::findValue($profile->id, $key->id, $channelId);

                if ($existing !== null) {
                    // Update existing value
                    if (! $dryRun) {
                        $existing->value = $valueData['value'];
                        $existing->locked = $valueData['locked'] ?? false;
                        $existing->save();
                    }
                    $result->addUpdated($keyCode, 'value');
                } else {
                    // Create new value
                    if (! $dryRun) {
                        $value = new ConfigValue;
                        $value->profile_id = $profile->id;
                        $value->key_id = $key->id;
                        $value->channel_id = $channelId;
                        $value->value = $valueData['value'];
                        $value->locked = $valueData['locked'] ?? false;
                        $value->save();
                    }
                    $result->addCreated($keyCode, 'value');
                }
            } catch (\Exception $e) {
                $result->addError("Failed to import value '{$keyCode}': ".$e->getMessage());
            }
        }
    }

    /**
     * Check if export version is compatible.
     */
    protected function isVersionCompatible(string $version): bool
    {
        // For now, only support exact version match
        // Can be extended to support backward compatibility
        $supported = ['1.0'];

        return in_array($version, $supported, true);
    }

    /**
     * Get profile for a workspace (or system).
     */
    protected function getProfile(?object $workspace): ?ConfigProfile
    {
        if ($workspace !== null) {
            return ConfigProfile::forWorkspace($workspace->id);
        }

        return ConfigProfile::system();
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
     * Export config to a file.
     *
     * @param  string  $path  File path (extension determines format)
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $includeSensitive  Include sensitive values
     *
     * @throws \RuntimeException If file cannot be written
     */
    public function exportToFile(
        string $path,
        ?object $workspace = null,
        bool $includeSensitive = false,
    ): void {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $content = match ($extension) {
            'yaml', 'yml' => $this->exportYaml($workspace, $includeSensitive),
            default => $this->exportJson($workspace, $includeSensitive),
        };

        $result = file_put_contents($path, $content);

        if ($result === false) {
            throw new \RuntimeException("Failed to write config export to: {$path}");
        }
    }

    /**
     * Import config from a file.
     *
     * @param  string  $path  File path (extension determines format)
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  bool  $dryRun  Preview changes without applying
     * @return ImportResult Import result with stats
     *
     * @throws \RuntimeException If file cannot be read
     */
    public function importFromFile(
        string $path,
        ?object $workspace = null,
        bool $dryRun = false,
    ): ImportResult {
        if (! file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read config file: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'yaml', 'yml' => $this->importYaml($content, $workspace, $dryRun),
            default => $this->importJson($content, $workspace, $dryRun),
        };
    }
}
