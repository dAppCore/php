<div class="space-y-6" @if($pollInterval > 0) wire:poll.{{ $pollInterval }}s @endif>
    <flux:heading size="xl">Activity Log</flux:heading>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Activities</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($this->statistics['total']) }}</div>
        </flux:card>

        <flux:card class="p-4">
            <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Created</div>
            <div class="mt-1 text-2xl font-semibold text-green-600">{{ number_format($this->statistics['by_event']['created'] ?? 0) }}</div>
        </flux:card>

        <flux:card class="p-4">
            <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Updated</div>
            <div class="mt-1 text-2xl font-semibold text-blue-600">{{ number_format($this->statistics['by_event']['updated'] ?? 0) }}</div>
        </flux:card>

        <flux:card class="p-4">
            <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Deleted</div>
            <div class="mt-1 text-2xl font-semibold text-red-600">{{ number_format($this->statistics['by_event']['deleted'] ?? 0) }}</div>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap gap-4">
            <flux:select wire:model.live="causerId" placeholder="All Users" class="w-48">
                @foreach ($this->causers as $id => $name)
                    <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="subjectType" placeholder="All Types" class="w-48">
                @foreach ($this->subjectTypes as $type => $label)
                    <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="eventType" placeholder="All Events" class="w-40">
                @foreach ($this->eventTypes as $type => $label)
                    <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="daysBack" class="w-40">
                @foreach ($this->dateRanges as $days => $label)
                    <flux:select.option value="{{ $days }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search activities..."
                icon="magnifying-glass"
                class="flex-1 min-w-48"
            />

            @if ($causerId || $subjectType || $eventType || $daysBack !== 30 || $search)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">
                    Clear Filters
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- Activity List --}}
    <flux:card>
        @if ($this->activities->isEmpty())
            <div class="py-12 text-center">
                <flux:icon.clock class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="sm" class="mt-4">No Activities Found</flux:heading>
                <flux:text class="mt-2">
                    @if ($causerId || $subjectType || $eventType || $search)
                        Try adjusting your filters to see more results.
                    @else
                        Activity logging is enabled but no activities have been recorded yet.
                    @endif
                </flux:text>
            </div>
        @else
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->activities as $activity)
                    @php
                        $formatted = $this->formatActivity($activity);
                    @endphp
                    <div
                        wire:key="activity-{{ $activity->id }}"
                        class="flex items-start gap-4 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors"
                        wire:click="showDetail({{ $activity->id }})"
                    >
                        {{-- Avatar --}}
                        <div class="flex-shrink-0">
                            @if ($formatted['actor'])
                                @if ($formatted['actor']['avatar'])
                                    <img
                                        src="{{ $formatted['actor']['avatar'] }}"
                                        alt="{{ $formatted['actor']['name'] }}"
                                        class="h-10 w-10 rounded-full object-cover"
                                    />
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ $formatted['actor']['initials'] }}
                                    </div>
                                @endif
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                                    <flux:icon.cog class="h-5 w-5 text-zinc-400" />
                                </div>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-white">
                                    {{ $formatted['actor']['name'] ?? 'System' }}
                                </span>
                                <span class="text-zinc-500 dark:text-zinc-400">
                                    {{ $formatted['description'] }}
                                </span>
                            </div>

                            @if ($formatted['subject'])
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $formatted['subject']['type'] }}:
                                    @if ($formatted['subject']['url'])
                                        <a href="{{ $formatted['subject']['url'] }}" wire:navigate class="text-violet-500 hover:text-violet-600" wire:click.stop>
                                            {{ $formatted['subject']['name'] }}
                                        </a>
                                    @else
                                        {{ $formatted['subject']['name'] }}
                                    @endif
                                </div>
                            @endif

                            @if ($formatted['changes'])
                                <div class="mt-2 text-xs">
                                    <div class="inline-flex flex-wrap items-center gap-1 rounded bg-zinc-100 px-2 py-1 font-mono text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300 max-w-full overflow-hidden">
                                        @php $changeCount = 0; @endphp
                                        @foreach ($formatted['changes']['new'] as $key => $newValue)
                                            @if (($formatted['changes']['old'][$key] ?? null) !== $newValue && $changeCount < 3)
                                                @if ($changeCount > 0)
                                                    <span class="mx-2 text-zinc-400">|</span>
                                                @endif
                                                <span class="font-semibold">{{ $key }}:</span>
                                                <span class="text-red-500 line-through truncate max-w-20">{{ is_array($formatted['changes']['old'][$key] ?? null) ? json_encode($formatted['changes']['old'][$key]) : ($formatted['changes']['old'][$key] ?? 'null') }}</span>
                                                <span class="mx-1">&rarr;</span>
                                                <span class="text-green-500 truncate max-w-20">{{ is_array($newValue) ? json_encode($newValue) : $newValue }}</span>
                                                @php $changeCount++; @endphp
                                            @endif
                                        @endforeach
                                        @if (count(array_filter($formatted['changes']['new'], fn($v, $k) => ($formatted['changes']['old'][$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH)) > 3)
                                            <span class="ml-2 text-zinc-400">+{{ count($formatted['changes']['new']) - 3 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="mt-2 text-xs text-zinc-400">
                                {{ $formatted['relative_time'] }}
                            </div>
                        </div>

                        {{-- Event Badge --}}
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium {{ $this->eventColor($formatted['event']) }}">
                                <flux:icon :name="$this->eventIcon($formatted['event'])" class="h-3 w-3" />
                                {{ ucfirst($formatted['event']) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($this->activities->hasPages())
                <div class="border-t border-zinc-200 dark:border-zinc-700 px-4 py-3">
                    {{ $this->activities->links() }}
                </div>
            @endif
        @endif
    </flux:card>

    {{-- Detail Modal --}}
    <flux:modal wire:model="showDetailModal" class="max-w-2xl">
        @if ($this->selectedActivity)
            @php
                $selected = $this->formatActivity($this->selectedActivity);
            @endphp
            <div class="space-y-6">
                <flux:heading size="lg">Activity Details</flux:heading>

                {{-- Activity Header --}}
                <div class="flex items-start gap-4">
                    @if ($selected['actor'])
                        @if ($selected['actor']['avatar'])
                            <img
                                src="{{ $selected['actor']['avatar'] }}"
                                alt="{{ $selected['actor']['name'] }}"
                                class="h-12 w-12 rounded-full object-cover"
                            />
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-lg font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                {{ $selected['actor']['initials'] }}
                            </div>
                        @endif
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <flux:icon.cog class="h-6 w-6 text-zinc-400" />
                        </div>
                    @endif

                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-medium text-zinc-900 dark:text-white">
                                {{ $selected['actor']['name'] ?? 'System' }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $this->eventColor($selected['event']) }}">
                                {{ ucfirst($selected['event']) }}
                            </span>
                        </div>
                        <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ $selected['description'] }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-400">
                            {{ $selected['relative_time'] }} &middot; {{ \Carbon\Carbon::parse($selected['timestamp'])->format('M j, Y \a\t g:i A') }}
                        </div>
                    </div>
                </div>

                {{-- Subject Info --}}
                @if ($selected['subject'])
                    <flux:card class="p-4">
                        <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-2">Subject</div>
                        <div class="flex items-center gap-2">
                            <flux:badge color="zinc">{{ $selected['subject']['type'] }}</flux:badge>
                            @if ($selected['subject']['url'])
                                <a href="{{ $selected['subject']['url'] }}" wire:navigate class="text-violet-500 hover:text-violet-600 font-medium">
                                    {{ $selected['subject']['name'] }}
                                </a>
                            @else
                                <span class="font-medium">{{ $selected['subject']['name'] }}</span>
                            @endif
                        </div>
                    </flux:card>
                @endif

                {{-- Changes Diff --}}
                @if ($selected['changes'] && (count($selected['changes']['old']) > 0 || count($selected['changes']['new']) > 0))
                    <div>
                        <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-3">Changes</div>
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Field</th>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Old Value</th>
                                        <th class="px-4 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">New Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($selected['changes']['new'] as $key => $newValue)
                                        @php
                                            $oldValue = $selected['changes']['old'][$key] ?? null;
                                        @endphp
                                        @if ($oldValue !== $newValue)
                                            <tr>
                                                <td class="px-4 py-2 font-mono text-zinc-900 dark:text-white">{{ $key }}</td>
                                                <td class="px-4 py-2 font-mono text-red-600 dark:text-red-400 break-all">
                                                    @if (is_array($oldValue))
                                                        <pre class="text-xs whitespace-pre-wrap">{{ json_encode($oldValue, JSON_PRETTY_PRINT) }}</pre>
                                                    @elseif ($oldValue === null)
                                                        <span class="text-zinc-400 italic">null</span>
                                                    @elseif (is_bool($oldValue))
                                                        {{ $oldValue ? 'true' : 'false' }}
                                                    @else
                                                        {{ $oldValue }}
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 font-mono text-green-600 dark:text-green-400 break-all">
                                                    @if (is_array($newValue))
                                                        <pre class="text-xs whitespace-pre-wrap">{{ json_encode($newValue, JSON_PRETTY_PRINT) }}</pre>
                                                    @elseif ($newValue === null)
                                                        <span class="text-zinc-400 italic">null</span>
                                                    @elseif (is_bool($newValue))
                                                        {{ $newValue ? 'true' : 'false' }}
                                                    @else
                                                        {{ $newValue }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Raw Properties --}}
                <flux:accordion>
                    <flux:accordion.item>
                        <flux:accordion.heading>
                            <span class="text-sm text-zinc-500">Raw Properties</span>
                        </flux:accordion.heading>
                        <flux:accordion.content>
                            <pre class="text-xs font-mono bg-zinc-100 dark:bg-zinc-800 p-3 rounded overflow-auto max-h-48">{{ json_encode($this->selectedActivity->properties, JSON_PRETTY_PRINT) }}</pre>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>

                {{-- Actions --}}
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="closeDetail">Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
