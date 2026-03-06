<div class="space-y-6">
    <flux:heading size="xl">Configuration</flux:heading>

    <div class="flex flex-wrap gap-4">
        <flux:select wire:model.live="scope" class="w-40">
            <flux:select.option value="system">System</flux:select.option>
            <flux:select.option value="workspace">Workspace</flux:select.option>
        </flux:select>

        @if ($scope === 'workspace')
            <flux:select wire:model.live="workspaceId" placeholder="Select workspace" class="w-64">
                <flux:select.option value="">Select workspace</flux:select.option>
                @foreach ($this->workspaces as $ws)
                    <flux:select.option value="{{ $ws->id }}">{{ $ws->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="category" placeholder="All categories">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach ($this->categories as $cat)
                <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search keys..." icon="magnifying-glass"
                    class="flex-1"/>
    </div>

    @if ($scope === 'workspace' && $this->selectedWorkspace)
        <flux:callout icon="information-circle" color="blue">
            Editing configuration for workspace: <strong>{{ $this->selectedWorkspace->name }}</strong>.
            Values inherit from system unless overridden.
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Key</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Value</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->keys as $key)
                @php
                    $isInherited = $this->isInherited($key);
                    $isLockedByParent = $this->isLockedByParent($key);
                    $isLocked = $this->isLocked($key);
                @endphp
                <flux:table.row wire:key="{{ $key->id }}" class="{{ $isInherited ? 'opacity-60' : '' }}">
                    <flux:table.cell>
                        <div>
                            <div class="font-mono text-sm">{{ $key->code }}</div>
                            @if ($key->description)
                                <div class="text-xs text-zinc-500">{{ $key->description }}</div>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $key->type->value }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($editingKeyId === $key->id)
                            @if ($key->type === \Core\Config\Enums\ConfigType::BOOL)
                                <flux:switch wire:model="editValue"/>
                            @elseif ($key->type === \Core\Config\Enums\ConfigType::INT)
                                <flux:input type="number" wire:model="editValue" class="w-32"/>
                            @elseif ($key->type === \Core\Config\Enums\ConfigType::JSON || $key->type === \Core\Config\Enums\ConfigType::ARRAY)
                                <flux:textarea wire:model="editValue" rows="3" class="font-mono text-xs"/>
                            @else
                                <flux:input wire:model="editValue"/>
                            @endif
                        @else
                            <div class="font-mono text-sm max-w-xs truncate">
                                @if (is_array($this->getValue($key)))
                                    {{ json_encode($this->getValue($key)) }}
                                @elseif (is_bool($this->getValue($key)))
                                    {{ $this->getValue($key) ? 'true' : 'false' }}
                                @else
                                    {{ $this->getValue($key) ?? '-' }}
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            @if ($editingKeyId === $key->id && !$isLockedByParent)
                                <flux:switch wire:model="editLocked" label="Lock"/>
                            @else
                                @if ($isLockedByParent)
                                    <flux:badge size="sm" color="red" icon="lock-closed">System locked</flux:badge>
                                @elseif ($isLocked)
                                    <flux:icon.lock-closed class="w-4 h-4 text-amber-500"/>
                                @endif

                                @if ($isInherited)
                                    <flux:badge size="sm" color="blue">Inherited</flux:badge>
                                @elseif ($scope === 'workspace' && $workspaceId)
                                    <flux:badge size="sm" color="green">Override</flux:badge>
                                @endif
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($editingKeyId === $key->id)
                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="save" :disabled="$isLockedByParent">Save
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancel">Cancel</flux:button>
                            </div>
                        @else
                            <div class="flex gap-2">
                                @if (!$isLockedByParent)
                                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $key->id }})">Edit
                                    </flux:button>
                                @endif

                                @if ($scope === 'workspace' && $workspaceId && !$isInherited)
                                    <flux:button size="sm" variant="ghost" color="red"
                                                 wire:click="clearOverride({{ $key->id }})"
                                                 wire:confirm="Clear this override and revert to inherited value?">
                                        Clear
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if ($this->keys->isEmpty())
        <flux:card class="text-center py-12">
            <flux:text>No configuration keys found.</flux:text>
        </flux:card>
    @endif
</div>
