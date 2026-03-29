# Front/Admin/Support

Builder utilities for constructing admin menu items.

## Files

- **MenuItemBuilder.php** -- Fluent builder for `AdminMenuProvider::adminMenuItems()` return arrays. Chainable API: `MenuItemBuilder::make('Label')->icon('cube')->href('/path')->inGroup('services')->entitlement('core.srv.x')->build()`. Supports route-based hrefs, active state callbacks (`activeOnRoute('hub.bio.*')`), children, badges, priority shortcuts (`->first()`, `->high()`, `->last()`), service keys, and custom attributes.

- **MenuItemGroup.php** -- Static factory for structural menu elements within children arrays. Creates separators (`::separator()`), section headers (`::header('Products', 'cube')`), collapsible groups (`::collapsible('Orders', $children)`), and dividers (`::divider('More')`). Also provides type-check helpers: `isSeparator()`, `isHeader()`, `isCollapsible()`, `isDivider()`, `isStructural()`, `isLink()`.

## Usage

```php
MenuItemBuilder::make('Commerce')
    ->icon('shopping-cart')
    ->inServices()
    ->entitlement('core.srv.commerce')
    ->children([
        MenuItemGroup::header('Products', 'cube'),
        MenuItemBuilder::child('All Products', '/products')->icon('list'),
        MenuItemGroup::separator(),
        MenuItemBuilder::child('Orders', '/orders')->icon('receipt'),
    ])
    ->build();
```
