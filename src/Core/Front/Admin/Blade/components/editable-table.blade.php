{{--
Editable Table Component

Supports inline editing with these cell types:
- checkbox: { type: 'checkbox', model: 'selected', value: 1 }
- switch: { type: 'switch', click: 'toggle(1)', checked: true }
- input: { type: 'input', change: 'update(1, $event.target.value)', value: 0, inputType: 'number', class: 'w-20' }
- select: { type: 'select', model: 'category', options: [{value: 'a', label: 'A'}] }
- badge: { type: 'badge', label: 'Active', color: 'green' }
- text: { type: 'text', bold: true, value: 'Title' } or { type: 'text', muted: true, value: 'Subtitle' }
- preview: { type: 'preview', color: '#f3f4f6' } or { type: 'preview', image: 'url' }
- actions: { type: 'actions', items: [{icon, click, title, confirm?}] }
- menu: { type: 'menu', items: [{icon, click, label}] }
- html: { type: 'html', content: '<span>...</span>' }
--}}

<div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    @if($selectable)
                        <th class="w-12 px-4 py-3">
                            <input
                                type="checkbox"
                                wire:model.live="selectAll"
                                class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700"
                            />
                        </th>
                    @endif
                    @foreach($processedColumns as $column)
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 {{ $column['alignClass'] }} {{ $column['width'] ? 'w-'.$column['width'] : '' }}">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse($rows as $rowIndex => $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="row-{{ $rowIndex }}">
                        @if($selectable)
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    wire:model.live="{{ $selectModel }}"
                                    value="{{ $row['_id'] ?? $rowIndex }}"
                                    class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                            </td>
                        @endif
                        @foreach($row['cells'] ?? $row as $cellIndex => $cell)
                            @if($cellIndex === '_id') @continue @endif
                            <td class="whitespace-nowrap px-4 py-3 {{ $cellAlignClass($cellIndex) }}">
                                @if(is_array($cell) && isset($cell['type']))
                                    @switch($cell['type'])
                                        @case('checkbox')
                                            <input
                                                type="checkbox"
                                                wire:model.live="{{ $cell['model'] }}"
                                                value="{{ $cell['value'] }}"
                                                class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            @break

                                        @case('switch')
                                            <button
                                                type="button"
                                                wire:click="{{ $cell['click'] }}"
                                                class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 {{ $cell['checked'] ? 'bg-violet-600' : 'bg-gray-200 dark:bg-gray-600' }}"
                                                role="switch"
                                                aria-checked="{{ $cell['checked'] ? 'true' : 'false' }}"
                                            >
                                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $cell['checked'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                            </button>
                                            @break

                                        @case('input')
                                            <input
                                                type="{{ $cell['inputType'] ?? 'text' }}"
                                                value="{{ $cell['value'] }}"
                                                wire:change="{{ $cell['change'] }}"
                                                class="block rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 {{ $cell['class'] ?? 'w-full' }}"
                                                @if(isset($cell['placeholder'])) placeholder="{{ $cell['placeholder'] }}" @endif
                                                @if(isset($cell['disabled']) && $cell['disabled']) disabled @endif
                                            />
                                            @break

                                        @case('select')
                                            <select
                                                wire:model.live="{{ $cell['model'] }}"
                                                class="block rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 {{ $cell['class'] ?? 'w-full' }}"
                                            >
                                                @foreach($cell['options'] as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                            @break

                                        @case('badge')
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-{{ $cell['color'] ?? 'gray' }}-100 text-{{ $cell['color'] ?? 'gray' }}-700 dark:bg-{{ $cell['color'] ?? 'gray' }}-900/30 dark:text-{{ $cell['color'] ?? 'gray' }}-400">
                                                @if(isset($cell['icon']))
                                                    <core:icon :name="$cell['icon']" class="mr-1 size-3" />
                                                @endif
                                                {{ $cell['label'] }}
                                            </span>
                                            @break

                                        @case('text')
                                            @if($cell['bold'] ?? false)
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cell['value'] }}</div>
                                            @elseif($cell['muted'] ?? false)
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $cell['value'] }}</div>
                                            @elseif($cell['mono'] ?? false)
                                                <code class="text-xs text-gray-400">{{ $cell['value'] }}</code>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $cell['value'] }}</span>
                                            @endif
                                            @if(isset($cell['subtitle']))
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $cell['subtitle'] }}</div>
                                            @endif
                                            @break

                                        @case('preview')
                                            @if(isset($cell['image']))
                                                <img src="{{ $cell['image'] }}" alt="" class="h-10 w-10 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                            @elseif(isset($cell['color']))
                                                <div class="h-10 w-10 rounded-lg border border-gray-200 dark:border-gray-700" style="background: {{ $cell['color'] }};"></div>
                                            @endif
                                            @break

                                        @case('actions')
                                            <div class="flex gap-1">
                                                @foreach($cell['items'] as $action)
                                                    <button
                                                        type="button"
                                                        wire:click="{{ $action['click'] }}"
                                                        @if(isset($action['confirm']))
                                                            wire:confirm="{{ $action['confirm'] }}"
                                                        @endif
                                                        class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 {{ $action['class'] ?? '' }}"
                                                        @if(isset($action['title'])) title="{{ $action['title'] }}" @endif
                                                    >
                                                        <core:icon name="{{ $action['icon'] }}" class="size-4" />
                                                    </button>
                                                @endforeach
                                            </div>
                                            @break

                                        @case('menu')
                                            <div x-data="{ open: false }" class="relative">
                                                <button
                                                    type="button"
                                                    @click="open = !open"
                                                    class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                                >
                                                    <core:icon name="ellipsis-horizontal" class="size-4" />
                                                </button>
                                                <div
                                                    x-show="open"
                                                    @click.outside="open = false"
                                                    x-transition
                                                    class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                                >
                                                    @foreach($cell['items'] as $item)
                                                        <button
                                                            type="button"
                                                            wire:click="{{ $item['click'] }}"
                                                            @click="open = false"
                                                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                                        >
                                                            @if(isset($item['icon']))
                                                                <core:icon name="{{ $item['icon'] }}" class="size-4 text-gray-400" />
                                                            @endif
                                                            {{ $item['label'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @break

                                        @case('html')
                                            {!! $cell['content'] !!}
                                            @break
                                    @endswitch
                                @elseif(is_array($cell))
                                    {{-- Legacy format support --}}
                                    @if(isset($cell['bold']))
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cell['bold'] }}</div>
                                    @elseif(isset($cell['muted']))
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $cell['muted'] }}</div>
                                    @elseif(isset($cell['badge']))
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-{{ $cell['color'] ?? 'gray' }}-100 text-{{ $cell['color'] ?? 'gray' }}-700 dark:bg-{{ $cell['color'] ?? 'gray' }}-900/30 dark:text-{{ $cell['color'] ?? 'gray' }}-400">
                                            {{ $cell['badge'] }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-900 dark:text-gray-100">{{ $cell }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspanCount() }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <core:icon :name="$emptyIcon" class="mx-auto mb-3 size-12 text-gray-300 dark:text-gray-600" />
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
