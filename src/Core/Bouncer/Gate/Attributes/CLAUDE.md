# Bouncer/Gate/Attributes/ — Action Gate PHP Attributes

## Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Action(name, scope?)]` | Method, Class | Declares an explicit action name for permission checking, overriding auto-resolution from controller/method names. Optional `scope` for resource-specific permissions. |

Without this attribute, action names are auto-resolved: `ProductController@store` becomes `product.store`.

```php
#[Action('product.create')]
public function store(Request $request) { ... }
```
