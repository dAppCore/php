{{-- Core Textarea - Thin wrapper around flux:textarea. Props: name, rows, placeholder, label, description, resize (none|vertical|horizontal|both), disabled, readonly, required, wire:model --}}
<flux:textarea {{ $attributes }}>{{ $slot }}</flux:textarea>
