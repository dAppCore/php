# Blade/pillbox

Pillbox (tag/chip input) sub-components for multi-value selection.

## Files

- **create.blade.php** -- "Create new" action within the pillbox dropdown
- **empty.blade.php** -- Empty state when no options match the search
- **input.blade.php** -- Search/filter input within the pillbox
- **option.blade.php** -- Selectable option in the dropdown list
- **search.blade.php** -- Search container wrapping the input and results
- **trigger.blade.php** -- The pillbox trigger showing selected items as removable pills

## Usage

```blade
<core:pillbox>
    <core:pillbox.trigger />
    <core:pillbox.search>
        <core:pillbox.input placeholder="Search tags..." />
        <core:pillbox.option value="php">PHP</core:pillbox.option>
        <core:pillbox.option value="go">Go</core:pillbox.option>
        <core:pillbox.empty>No matches</core:pillbox.empty>
        <core:pillbox.create>Add new tag</core:pillbox.create>
    </core:pillbox.search>
</core:pillbox>
```
