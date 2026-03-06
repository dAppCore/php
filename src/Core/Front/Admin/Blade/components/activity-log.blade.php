{{--
Activity Log Component

Full activity log display with avatars, diffs, and detailed timestamps.

Each item in $items array:
- actor: { name: 'John', avatar?: 'url', initials?: 'J' } or null for system
- description: 'updated the post'
- subject: { type: 'Post', name: 'My Article', url?: 'link' } (optional)
- changes: { old: {field: 'value'}, new: {field: 'new_value'} } (optional)
- event: 'created' | 'updated' | 'deleted' | string
- timestamp: Carbon instance or string
--}}

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    @forelse($items as $item)
        <div class="flex items-start gap-4 border-b border-gray-100 p-4 last:border-0 dark:border-gray-700" wire:key="activity-{{ $loop->index }}">
            {{-- Avatar --}}
            <div class="flex-shrink-0">
                @if(isset($item['actor']))
                    @if(isset($item['actor']['avatar']))
                        <img
                            src="{{ $item['actor']['avatar'] }}"
                            alt="{{ $item['actor']['name'] }}"
                            class="h-10 w-10 rounded-full object-cover"
                        />
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-sm font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $item['actor']['initials'] ?? substr($item['actor']['name'] ?? 'U', 0, 1) }}
                        </div>
                    @endif
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                        <core:icon name="cog" class="size-5 text-gray-400" />
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ $item['actor']['name'] ?? 'System' }}
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $item['description'] }}
                    </span>
                </div>

                @if(isset($item['subject']))
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $item['subject']['type'] }}:
                        @if(isset($item['subject']['url']))
                            <a href="{{ $item['subject']['url'] }}" wire:navigate class="text-violet-500 hover:text-violet-600">
                                {{ $item['subject']['name'] }}
                            </a>
                        @else
                            {{ $item['subject']['name'] }}
                        @endif
                    </div>
                @endif

                @if(isset($item['changes']['old']) && isset($item['changes']['new']))
                    <div class="mt-2 text-xs">
                        <div class="inline-flex flex-wrap items-center gap-1 rounded bg-gray-100 px-2 py-1 font-mono text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            @foreach($item['changes']['new'] as $key => $newValue)
                                @if(($item['changes']['old'][$key] ?? null) !== $newValue)
                                    <span class="text-red-500 line-through">{{ $formatValue($item['changes']['old'][$key] ?? null) }}</span>
                                    <span class="mx-1">&rarr;</span>
                                    <span class="text-green-500">{{ $formatValue($newValue) }}</span>
                                    @if(!$loop->last)<span class="mx-2 text-gray-400">|</span>@endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-2 text-xs text-gray-400">
                    {{ $formatTimestamp($item['timestamp'] ?? null)['relative'] }}
                    @if($formatTimestamp($item['timestamp'] ?? null)['absolute'])
                        <span class="mx-1">&middot;</span>
                        {{ $formatTimestamp($item['timestamp'] ?? null)['absolute'] }}
                    @endif
                </div>
            </div>

            {{-- Event Badge --}}
            <div class="flex-shrink-0">
                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $eventColor($item['event'] ?? 'activity') }}">
                    {{ $item['event'] ?? 'activity' }}
                </span>
            </div>
        </div>
    @empty
        <div class="px-4 py-12 text-center">
            <core:icon name="{{ $emptyIcon }}" class="mx-auto size-10 text-gray-300 dark:text-gray-600" />
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                {{ $empty }}
            </p>
        </div>
    @endforelse
</div>

@if($pagination && $pagination->hasPages())
    <div class="mt-4">
        {{ $pagination->links() }}
    </div>
@endif
