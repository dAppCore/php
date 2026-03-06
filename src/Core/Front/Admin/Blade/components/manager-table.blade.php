@php
    // Badge color class mappings for Tailwind JIT purging
    $badgeClasses = fn($color) => match($color) {
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
        'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400',
    };
@endphp
<div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
    {{-- Bulk action bar --}}
    @if(isset($selectable) && $selectable && isset($selected) && count($selected) > 0)
        <div class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 px-6 py-3 flex items-center justify-between">
            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ count($selected) }} {{ count($selected) === 1 ? 'item' : 'items' }} selected
            </span>
            <div class="flex items-center gap-2">
                {{ $bulkActions ?? '' }}
            </div>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    @if(isset($selectable) && $selectable)
                        <th class="w-12 px-4 py-3">
                            <flux:checkbox wire:model.live="selectAll" />
                        </th>
                    @endif
                    @foreach($processedColumns as $column)
                        <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 {{ $column['alignClass'] }}">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse($rows as $rowIndex => $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        @if(isset($selectable) && $selectable && isset($rowIds[$rowIndex]))
                            <td class="w-12 px-4 py-4">
                                <flux:checkbox wire:model.live="selected" value="{{ $rowIds[$rowIndex] }}" />
                            </td>
                        @endif
                        @foreach($row as $index => $cell)
                            <td class="whitespace-nowrap px-6 py-4 {{ $cellAlignClass($index) }}">
                                @if(is_array($cell))
                                    @if(isset($cell['lines']))
                                        <div class="space-y-0.5">
                                            @foreach($cell['lines'] as $line)
                                                @if(isset($line['bold']))
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $line['bold'] }}</div>
                                                @elseif(isset($line['muted']))
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $line['muted'] }}</div>
                                                @elseif(isset($line['mono']))
                                                    <code class="text-xs text-gray-400">{{ $line['mono'] }}</code>
                                                @elseif(isset($line['badge']))
                                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses($line['color'] ?? 'gray') }}">
                                                        @if(isset($line['icon']))
                                                            <core:icon name="{{ $line['icon'] }}" class="size-3" />
                                                        @endif
                                                        {{ $line['badge'] }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @elseif(isset($cell['bold']))
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cell['bold'] }}</div>
                                    @elseif(isset($cell['muted']))
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $cell['muted'] }}</div>
                                    @elseif(isset($cell['mono']))
                                        <code class="text-xs text-gray-400">{{ $cell['mono'] }}</code>
                                    @elseif(isset($cell['badge']))
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses($cell['color'] ?? 'gray') }}">
                                            @if(isset($cell['icon']))
                                                <core:icon name="{{ $cell['icon'] }}" class="size-3" />
                                            @endif
                                            {{ $cell['badge'] }}
                                        </span>
                                    @elseif(isset($cell['badges']))
                                        <div class="flex flex-col gap-1">
                                            @foreach($cell['badges'] as $badge)
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses($badge['color'] ?? 'gray') }}">
                                                    @if(isset($badge['icon']))
                                                        <core:icon name="{{ $badge['icon'] }}" class="size-3" />
                                                    @endif
                                                    {{ $badge['label'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @elseif(isset($cell['actions']))
                                        <div class="flex gap-1">
                                            @foreach($cell['actions'] as $action)
                                                <button
                                                    type="button"
                                                    wire:click="{{ $action['click'] }}"
                                                    @if(isset($action['confirm'])) wire:confirm="{{ $action['confirm'] }}" @endif
                                                    class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 {{ $action['class'] ?? '' }}"
                                                    @if(isset($action['title'])) title="{{ $action['title'] }}" @endif
                                                >
                                                    <core:icon name="{{ $action['icon'] }}" class="size-4" />
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif(isset($cell['link']))
                                        <a
                                            href="{{ $cell['href'] }}"
                                            @if(isset($cell['target'])) target="{{ $cell['target'] }}" @endif
                                            class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        >{{ $cell['link'] }}</a>
                                    @elseif(isset($cell['trustedHtml']))
                                        {{-- WARNING: Only use trustedHtml with already-sanitised server-rendered content --}}
                                        {!! $cell['trustedHtml'] !!}
                                    @endif
                                @else
                                    <span class="text-gray-900 dark:text-gray-100">{{ $cell }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + (isset($selectable) && $selectable ? 1 : 0) }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <core:icon name="{{ $emptyIcon }}" class="mx-auto mb-3 size-12 text-gray-300 dark:text-gray-600" />
                            <p>{{ $empty }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pagination && $pagination->hasPages())
        <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
            {{ $pagination->links() }}
        </div>
    @endif
</div>
