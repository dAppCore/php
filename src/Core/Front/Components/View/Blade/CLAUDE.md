# Components/View/Blade

Root directory for all core anonymous Blade components. Registered under the `core::` namespace and accessible via `<core:xyz>` tag syntax.

## Top-Level Components (48 files)

Each `.blade.php` file is the parent component. Sub-components live in matching subdirectories.

| Component | Description |
|-----------|-------------|
| accordion | Collapsible content sections |
| autocomplete | Typeahead search input |
| avatar | User/entity avatar display |
| badge | Status/count badge |
| button | Action button (primary, secondary, danger, ghost) |
| calendar | Calendar date display |
| callout | Notice/alert box |
| card | Content card container |
| chart | SVG chart container |
| checkbox | Checkbox input |
| command | Command palette (Cmd+K) |
| composer | Content composer/editor wrapper |
| context | Context menu |
| date-picker | Date selection input |
| description | Description list/text |
| dropdown | Dropdown menu trigger |
| editor | Rich text editor |
| error | Inline error message |
| field | Form field wrapper (label + input + error) |
| file-item | File list item display |
| file-upload | File upload input |
| heading | Section heading (h1-h6) |
| icon | FontAwesome icon renderer |
| input | Text input |
| kanban | Kanban board |
| label | Form label |
| layout | HLCRF layout container |
| main | Main content area |
| menu | Dropdown menu panel |
| modal | Modal dialog |
| navbar | Navigation bar |
| navlist | Navigation list (sidebar) |
| navmenu | Navigation menu |
| pillbox | Tag/chip multi-select input |
| popover | Popover tooltip/panel |
| radio | Radio button input |
| select | Dropdown select |
| separator | Visual divider |
| slider | Range slider input |
| subheading | Secondary heading text |
| switch | Toggle switch |
| tab | Tab trigger |
| table | Data table |
| tabs | Tab container with panels |
| text | Body text |
| textarea | Multi-line text input |
| time-picker | Time selection input |
| tooltip | Hover tooltip |

## Subdirectories

Each subdirectory contains sub-components (e.g., `table/row.blade.php` = `<core:table.row>`). See individual `CLAUDE.md` files in each subdirectory.
