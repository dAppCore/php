<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\View\Modal\Admin;

use Core\Config\ConfigService;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigValue;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Workspace configuration panel.
 *
 * @property-read int $depth
 * @property-read array<string> $namespaces
 * @property-read ConfigProfile|null $workspaceProfile
 * @property-read ConfigProfile $systemProfile
 * @property-read object|null $workspace
 * @property-read string $prefix
 * @property-read array<int, array{namespace: string, label: string, keys: \Illuminate\Support\Collection}> $tabs
 */
class WorkspaceConfig extends Component
{
    public ?string $path = null;

    protected ConfigService $config;

    /**
     * Workspace service instance (from Tenant module when available).
     */
    protected ?object $workspaceService = null;

    public function boot(ConfigService $config): void
    {
        $this->config = $config;

        // Try to resolve WorkspaceService if Tenant module is installed
        if (class_exists(\Core\Tenant\Services\WorkspaceService::class)) {
            $this->workspaceService = app(\Core\Tenant\Services\WorkspaceService::class);
        }
    }

    public function mount(?string $path = null): void
    {
        $this->path = $path;
    }

    #[On('workspace-changed')]
    public function workspaceChanged(): void
    {
        unset($this->workspace);
        unset($this->workspaceProfile);
    }

    public function navigate(string $path): void
    {
        $this->path = $path;
        unset($this->prefix);
        unset($this->depth);
        unset($this->tabs);
        unset($this->currentKeys);
    }

    /**
     * Get current workspace (requires Tenant module).
     *
     * @return object|null Workspace model instance or null
     */
    #[Computed]
    public function workspace(): ?object
    {
        return $this->workspaceService?->currentModel();
    }

    #[Computed]
    public function prefix(): string
    {
        return $this->path ? str_replace('/', '.', $this->path) : '';
    }

    #[Computed]
    public function depth(): int
    {
        return $this->path ? count(explode('/', $this->path)) : 0;
    }

    #[Computed]
    public function namespaces(): array
    {
        return ConfigKey::orderBy('code')
            ->get()
            ->map(fn ($key) => explode('.', $key->code)[0])
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function navItems(): array
    {
        return collect($this->namespaces)->map(fn ($ns) => [
            'label' => ucfirst($ns),
            'action' => "navigate('{$ns}')",
            'current' => str_starts_with($this->path ?? '', $ns),
        ])->all();
    }

    #[Computed]
    public function tabs(): array
    {
        if ($this->depth !== 1) {
            return [];
        }

        $prefix = $this->prefix.'.';

        return ConfigKey::where('code', 'like', $prefix.'%')
            ->orderBy('code')
            ->get()
            ->filter(fn ($key) => count(explode('.', $key->code)) >= 3)
            ->map(fn ($key) => explode('.', $key->code)[1])
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function tabItems(): array
    {
        return collect($this->tabs)->map(fn ($t) => [
            'label' => ucfirst($t),
            'action' => "navigate('{$this->prefix}/{$t}')",
            'selected' => str_contains($this->path ?? '', '/'.$t),
        ])->all();
    }

    #[Computed]
    public function currentKeys(): array
    {
        if (! $this->path) {
            return [];
        }

        $prefix = $this->prefix.'.';

        $allKeys = ConfigKey::where('code', 'like', $prefix.'%')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        // Direct children: prefix + one segment (no dots in remainder)
        $matches = array_filter($allKeys, fn ($code) => ! str_contains(substr($code, strlen($prefix)), '.'));

        return ConfigKey::whereIn('code', $matches)
            ->orderBy('code')
            ->get()
            ->all();
    }

    #[Computed]
    public function workspaceProfile(): ?ConfigProfile
    {
        if (! $this->workspace) {
            return null;
        }

        $systemProfile = ConfigProfile::ensureSystem();

        return ConfigProfile::ensureWorkspace($this->workspace->id, $systemProfile->id);
    }

    #[Computed]
    public function systemProfile(): ConfigProfile
    {
        return ConfigProfile::ensureSystem();
    }

    public function settingName(string $code): string
    {
        $parts = explode('.', $code);

        return end($parts) ?: $code;
    }

    public function getValue(ConfigKey $key): mixed
    {
        if (! $this->workspaceProfile) {
            return $key->getTypedDefault();
        }

        $value = ConfigValue::findValue($this->workspaceProfile->id, $key->id);

        if ($value !== null) {
            return $value->getTypedValue();
        }

        $systemValue = ConfigValue::findValue($this->systemProfile->id, $key->id);

        return $systemValue?->getTypedValue() ?? $key->getTypedDefault();
    }

    public function getInheritedValue(ConfigKey $key): mixed
    {
        $value = ConfigValue::findValue($this->systemProfile->id, $key->id);

        return $value?->getTypedValue() ?? $key->getTypedDefault();
    }

    public function isInherited(ConfigKey $key): bool
    {
        if (! $this->workspaceProfile) {
            return true;
        }

        $workspaceValue = ConfigValue::findValue($this->workspaceProfile->id, $key->id);

        return $workspaceValue === null;
    }

    public function isLockedBySystem(ConfigKey $key): bool
    {
        $value = ConfigValue::findValue($this->systemProfile->id, $key->id);

        return $value?->isLocked() ?? false;
    }

    public function toggleBool(int $keyId): void
    {
        $key = ConfigKey::find($keyId);
        if (! $key || ! $this->workspaceProfile) {
            return;
        }

        if ($this->isLockedBySystem($key)) {
            return;
        }

        $currentValue = $this->getValue($key);
        $this->config->set($key->code, ! $currentValue, $this->workspaceProfile, false);
    }

    public function updateValue(int $keyId, mixed $value): void
    {
        $key = ConfigKey::find($keyId);
        if (! $key || ! $this->workspaceProfile) {
            return;
        }

        if ($this->isLockedBySystem($key)) {
            return;
        }

        $this->config->set($key->code, $value, $this->workspaceProfile, false);
    }

    public function clearOverride(int $keyId): void
    {
        if (! $this->workspaceProfile) {
            return;
        }

        ConfigValue::where('profile_id', $this->workspaceProfile->id)
            ->where('key_id', $keyId)
            ->delete();

        $this->dispatch('config-cleared');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('core.config::admin.workspace-config')
            ->layout('hub::admin.layouts.app', ['title' => 'Settings']);
    }
}
