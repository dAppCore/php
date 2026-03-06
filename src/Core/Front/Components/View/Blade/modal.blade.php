{{-- Core Modal - Thin wrapper around flux:modal. Props: name, maxWidth (sm|md|lg|xl|2xl), variant (default|flyout), position (left|right), closeable --}}
<flux:modal {{ $attributes }}>{{ $slot }}</flux:modal>
