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
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Configuration panel for managing config keys and values.
 *
 * @property-read ConfigProfile $activeProfile
 * @property-read array<string> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection $workspaces
 */
class ConfigPanel extends Component
{
    #[Url]
    public string $category = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $scope = 'system';

    #[Url]
    public ?int $workspaceId = null;

    public ?int $editingKeyId = null;

    public mixed $editValue = null;

    public bool $editLocked = false;

    protected ConfigService $config;

    public function boot(ConfigService $config): void
    {
        $this->config = $config;
    }

    public function mount(): void
    {
        $this->checkHadesAccess();
    }

    private function checkHadesAccess(): void
    {
        if (! auth()->user()?->isHades()) {
            abort(403, 'Hades access required');
        }
    }

    #[Computed]
    public function categories(): array
    {
        return ConfigKey::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Get all workspaces (requires Tenant module).
     */
    #[Computed]
    public function workspaces(): \Illuminate\Database\Eloquent\Collection
    {
        if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return \Core\Tenant\Models\Workspace::orderBy('name')->get();
    }

    #[Computed]
    public function keys(): \Illuminate\Database\Eloquent\Collection
    {
        return ConfigKey::query()
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where('code', 'LIKE', "%{$this->search}%"))
            ->orderBy('category')
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function activeProfile(): ConfigProfile
    {
        if ($this->scope === 'workspace' && $this->workspaceId) {
            $systemProfile = ConfigProfile::ensureSystem();

            return ConfigProfile::ensureWorkspace($this->workspaceId, $systemProfile->id);
        }

        return ConfigProfile::ensureSystem();
    }

    /**
     * Get selected workspace (requires Tenant module).
     *
     * @return object|null Workspace model instance or null
     */
    #[Computed]
    public function selectedWorkspace(): ?object
    {
        if ($this->workspaceId && class_exists(\Core\Tenant\Models\Workspace::class)) {
            return \Core\Tenant\Models\Workspace::find($this->workspaceId);
        }

        return null;
    }

    public function updatedScope(): void
    {
        if ($this->scope === 'system') {
            $this->workspaceId = null;
        }
        $this->cancel();
    }

    public function updatedWorkspaceId(): void
    {
        $this->cancel();
    }

    public function getValue(ConfigKey $key): mixed
    {
        $value = ConfigValue::findValue($this->activeProfile->id, $key->id);

        return $value?->getTypedValue() ?? $key->getTypedDefault();
    }

    public function getInheritedValue(ConfigKey $key): mixed
    {
        if ($this->scope !== 'workspace') {
            return null;
        }

        $systemProfile = ConfigProfile::ensureSystem();
        $value = ConfigValue::findValue($systemProfile->id, $key->id);

        return $value?->getTypedValue();
    }

    public function isInherited(ConfigKey $key): bool
    {
        if ($this->scope !== 'workspace') {
            return false;
        }

        $workspaceValue = ConfigValue::findValue($this->activeProfile->id, $key->id);

        return $workspaceValue === null;
    }

    public function isLocked(ConfigKey $key): bool
    {
        $value = ConfigValue::findValue($this->activeProfile->id, $key->id);

        return $value?->isLocked() ?? false;
    }

    public function isLockedByParent(ConfigKey $key): bool
    {
        if ($this->scope !== 'workspace') {
            return false;
        }

        $systemProfile = ConfigProfile::ensureSystem();
        $value = ConfigValue::findValue($systemProfile->id, $key->id);

        return $value?->isLocked() ?? false;
    }

    public function edit(int $keyId): void
    {
        $key = ConfigKey::find($keyId);
        if ($key === null) {
            return;
        }

        $this->editingKeyId = $keyId;
        $this->editValue = $this->getValue($key);
        $this->editLocked = $this->isLocked($key);
    }

    public function save(): void
    {
        if ($this->editingKeyId === null) {
            return;
        }

        $key = ConfigKey::find($this->editingKeyId);
        if ($key === null) {
            return;
        }

        // Check if parent has locked this key
        if ($this->isLockedByParent($key)) {
            $this->dispatch('config-error', message: 'This key is locked at system level');

            return;
        }

        $this->config->set(
            $key->code,
            $this->editValue,
            $this->activeProfile,
            $this->editLocked,
        );

        $this->editingKeyId = null;
        $this->editValue = null;
        $this->editLocked = false;

        $this->dispatch('config-saved');
    }

    public function cancel(): void
    {
        $this->editingKeyId = null;
        $this->editValue = null;
        $this->editLocked = false;
    }

    public function toggleLock(int $keyId): void
    {
        $key = ConfigKey::find($keyId);
        if ($key === null) {
            return;
        }

        if ($this->isLocked($key)) {
            $this->config->unlock($key->code, $this->activeProfile);
        } else {
            $this->config->lock($key->code, $this->activeProfile);
        }
    }

    public function clearOverride(int $keyId): void
    {
        if ($this->scope !== 'workspace') {
            return;
        }

        $key = ConfigKey::find($keyId);
        if ($key === null) {
            return;
        }

        ConfigValue::where('profile_id', $this->activeProfile->id)
            ->where('key_id', $key->id)
            ->delete();

        $this->dispatch('config-cleared');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('core.config::admin.config-panel')
            ->layout('hub::admin.layouts.app', ['title' => 'Configuration']);
    }
}
