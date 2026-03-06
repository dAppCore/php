<div class="flex h-full">
    {{-- Sidebar --}}
    <core:card class="bg-white dark:bg-gray-800 shadow-xs rounded-xl w-48 shrink-0">
        <core:heading size="sm" class="mb-4">Settings</core:heading>

        <core:navlist :items="$this->navItems"/>
    </core:card>

    {{-- Main Content --}}
    <core:main class="flex-1 p-6">
        @if (!$this->workspace)
            <core:card class="text-center py-12">
                <core:icon name="exclamation-triangle" class="w-12 h-12 text-amber-500 mx-auto mb-4"/>
                <core:heading size="lg">No Workspace</core:heading>
                <core:text>Select a workspace from the menu above.</core:text>
            </core:card>
        @elseif ($path)
            <div class="space-y-6">
                <core:heading size="lg">{{ ucfirst(str_replace('/', ' / ', $path)) }}</core:heading>

                @if ($this->tabItems)
                    <core:tabs variant="segmented" :items="$this->tabItems"/>
                @endif

                {{-- Settings list --}}
                <div class="space-y-4">
                    @forelse ($this->currentKeys as $key)
                        <core:card class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <core:heading size="sm">{{ $this->settingName($key->code) }}</core:heading>
                                    @if ($key->description)
                                        <core:text size="sm" class="text-zinc-500">{{ $key->description }}</core:text>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <core:badge size="sm" color="zinc">{{ $key->type->value }}</core:badge>
                                    @if ($this->isLockedBySystem($key))
                                        <core:badge size="sm" color="red" icon="lock-closed">Locked</core:badge>
                                    @elseif ($this->isInherited($key))
                                        <core:badge size="sm" color="blue">Inherited</core:badge>
                                    @else
                                        <core:badge size="sm" color="green">Custom</core:badge>
                                    @endif
                                </div>
                            </div>

                            @if ($this->isLockedBySystem($key))
                                <div class="font-mono text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded">
                                    @php $val = $this->getValue($key); @endphp
                                    @if (is_array($val))
                                        {{ json_encode($val, JSON_PRETTY_PRINT) }}
                                    @elseif (is_bool($val))
                                        {{ $val ? 'true' : 'false' }}
                                    @else
                                        {{ $val ?? '-' }}
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center gap-4">
                                    <div class="flex-1">
                                        @if ($key->type === \Core\Config\Enums\ConfigType::BOOL)
                                            <core:switch
                                                wire:click="toggleBool({{ $key->id }})"
                                                :checked="$this->getValue($key)"
                                            />
                                        @elseif ($key->type === \Core\Config\Enums\ConfigType::INT)
                                            <core:input
                                                type="number"
                                                wire:change="updateValue({{ $key->id }}, $event.target.value)"
                                                value="{{ $this->getValue($key) }}"
                                            />
                                        @elseif ($key->type === \Core\Config\Enums\ConfigType::JSON || $key->type === \Core\Config\Enums\ConfigType::ARRAY)
                                            <core:textarea
                                                wire:change="updateValue({{ $key->id }}, $event.target.value)"
                                                rows="3"
                                                class="font-mono text-sm"
                                            >{{ is_array($this->getValue($key)) ? json_encode($this->getValue($key), JSON_PRETTY_PRINT) : $this->getValue($key) }}</core:textarea>
                                        @else
                                            <core:input
                                                wire:change="updateValue({{ $key->id }}, $event.target.value)"
                                                value="{{ $this->getValue($key) }}"
                                            />
                                        @endif
                                    </div>
                                    @if (!$this->isInherited($key))
                                        <core:button
                                            wire:click="clearOverride({{ $key->id }})"
                                            wire:confirm="Reset to inherited value?"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-uturn-left"
                                        />
                                    @endif
                                </div>

                                @if ($this->isInherited($key))
                                    @php $inherited = $this->getInheritedValue($key); @endphp
                                    <core:text size="xs" class="text-zinc-400">
                                        Default: {{ is_array($inherited) ? json_encode($inherited) : ($inherited ?? 'none') }}
                                    </core:text>
                                @endif
                            @endif
                        </core:card>
                    @empty
                        <core:text class="text-zinc-500">No settings in this group.</core:text>
                    @endforelse
                </div>
            </div>
        @else
            <div class="flex items-center justify-center h-full text-zinc-500">
                <div class="text-center">
                    <core:icon name="cog-6-tooth" class="w-12 h-12 mx-auto mb-4 opacity-50"/>
                    <core:text>Select a category from the sidebar</core:text>
                </div>
            </div>
        @endif
    </core:main>
</div>
