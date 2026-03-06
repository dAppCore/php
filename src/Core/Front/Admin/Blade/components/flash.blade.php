@props([
    'key' => 'message',
    'errorKey' => 'error',
])

@if (session()->has($key))
    <div class="mb-4 rounded-lg border border-green-400 bg-green-100 px-4 py-3 text-green-700 dark:border-green-700 dark:bg-green-900/30 dark:text-green-400">
        {{ session($key) }}
    </div>
@endif

@if (session()->has($errorKey))
    <div class="mb-4 rounded-lg border border-red-400 bg-red-100 px-4 py-3 text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-400">
        {{ session($errorKey) }}
    </div>
@endif
