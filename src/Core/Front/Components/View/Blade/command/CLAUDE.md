# Blade/command

Command palette (Cmd+K) sub-components.

## Files

- **empty.blade.php** -- Empty state shown when no results match the search query
- **input.blade.php** -- Search input field within the command palette
- **item.blade.php** -- Individual command/action item in the results list
- **items.blade.php** -- Results list container wrapping command items

## Usage

```blade
<core:command>
    <core:command.input placeholder="Search..." />
    <core:command.items>
        <core:command.item>Go to Dashboard</core:command.item>
        <core:command.item>Create New...</core:command.item>
    </core:command.items>
    <core:command.empty>No results found</core:command.empty>
</core:command>
```
