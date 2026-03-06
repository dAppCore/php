{{-- Core Select - Thin wrapper around flux:select. Props: label, description, placeholder, variant, size, disabled, invalid, multiple, searchable, clearable, filter --}}
<flux:select {{ $attributes }}>{{ $slot }}</flux:select>
