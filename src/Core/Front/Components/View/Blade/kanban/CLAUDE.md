# Blade/kanban

Kanban board components.

## Files

- **card.blade.php** -- Individual kanban card (draggable item within a column)
- **column.blade.php** -- Kanban column container

## Subdirectories

- **column/** -- `cards.blade.php` (card list container), `footer.blade.php` (column footer with add action), `header.blade.php` (column title and count)

## Usage

```blade
<core:kanban>
    <core:kanban.column>
        <core:kanban.column.header>To Do</core:kanban.column.header>
        <core:kanban.column.cards>
            <core:kanban.card>Task 1</core:kanban.card>
        </core:kanban.column.cards>
        <core:kanban.column.footer />
    </core:kanban.column>
</core:kanban>
```
