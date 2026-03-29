# Blade/table

Table sub-components for data tables.

## Files

- **cell.blade.php** -- Individual table cell (`<td>`)
- **column.blade.php** -- Column header (`<th>`) with optional sorting
- **columns.blade.php** -- Column header row container (`<thead><tr>`)
- **row.blade.php** -- Table row (`<tr>`)
- **rows.blade.php** -- Table body container (`<tbody>`)

## Usage

```blade
<core:table>
    <core:table.columns>
        <core:table.column>Name</core:table.column>
        <core:table.column>Status</core:table.column>
    </core:table.columns>
    <core:table.rows>
        <core:table.row>
            <core:table.cell>Item 1</core:table.cell>
            <core:table.cell>Active</core:table.cell>
        </core:table.row>
    </core:table.rows>
</core:table>
```
