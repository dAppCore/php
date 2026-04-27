# Blade/accordion

Accordion (collapsible section) components.

## Files

- **content.blade.php** -- Collapsible content panel of an accordion item. Hidden/shown based on accordion state.
- **heading.blade.php** -- Clickable header that toggles the accordion item's content visibility.
- **item.blade.php** -- Single accordion item wrapping a heading + content pair.

## Usage

```blade
<core:accordion>
    <core:accordion.item>
        <core:accordion.heading>Section Title</core:accordion.heading>
        <core:accordion.content>Hidden content here</core:accordion.content>
    </core:accordion.item>
</core:accordion>
```
