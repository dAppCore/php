# HLCRF Deep Dive

This guide provides an in-depth look at the HLCRF (Header-Left-Content-Right-Footer) layout system, covering all layout combinations, the ID system, responsive patterns, and complex real-world examples.

## Layout Combinations

HLCRF supports any combination of its five regions. The variant name describes which regions are present.

### All Possible Combinations

| Variant | Regions | Use Case |
|---------|---------|----------|
| `C` | Content only | Simple content pages |
| `HC` | Header + Content | Landing pages |
| `CF` | Content + Footer | Article pages |
| `HCF` | Header + Content + Footer | Standard pages |
| `LC` | Left + Content | App with navigation |
| `CR` | Content + Right | Content with sidebar |
| `LCR` | Left + Content + Right | Three-column layout |
| `HLC` | Header + Left + Content | Admin dashboard |
| `HCR` | Header + Content + Right | Blog with widgets |
| `LCF` | Left + Content + Footer | App with footer |
| `CRF` | Content + Right + Footer | Blog layout |
| `HLCF` | Header + Left + Content + Footer | Standard admin |
| `HCRF` | Header + Content + Right + Footer | Blog layout |
| `HLCR` | Header + Left + Content + Right | Full admin |
| `LCRF` | Left + Content + Right + Footer | Complex app |
| `HLCRF` | All five regions | Complete layout |

### Content-Only (C)

Minimal layout for simple content:

```php
use Core\Front\Components\Layout;

$layout = Layout::make('C')
    ->c('<main>Simple content without chrome</main>');

echo $layout->render();
```

**Output:**
```html
<div class="hlcrf-layout" data-layout="root">
    <div class="hlcrf-body flex flex-1">
        <main class="hlcrf-content flex-1" data-slot="C">
            <div data-block="C-0">
                <main>Simple content without chrome</main>
            </div>
        </main>
    </div>
</div>
```

### Header + Content + Footer (HCF)

Standard page layout:

```php
$layout = Layout::make('HCF')
    ->h('<nav>Site Navigation</nav>')
    ->c('<article>Page Content</article>')
    ->f('<footer>Copyright 2026</footer>');
```

### Left + Content (LC)

Application with navigation sidebar:

```php
$layout = Layout::make('LC')
    ->l('<nav class="w-64">App Menu</nav>')
    ->c('<main>App Content</main>');
```

### Three-Column (LCR)

Full three-column layout:

```php
$layout = Layout::make('LCR')
    ->l('<nav>Navigation</nav>')
    ->c('<main>Content</main>')
    ->r('<aside>Widgets</aside>');
```

### Full Admin (HLCRF)

Complete admin panel:

```php
$layout = Layout::make('HLCRF')
    ->h('<header>Admin Header</header>')
    ->l('<nav>Sidebar</nav>')
    ->c('<main>Dashboard</main>')
    ->r('<aside>Quick Actions</aside>')
    ->f('<footer>Status Bar</footer>');
```

## The ID System

Every HLCRF element receives a unique, hierarchical ID that describes its position in the layout tree.

### ID Format

```
{Region}-{Index}[-{NestedRegion}-{NestedIndex}]...
```

**Components:**
- **Region Letter** - `H`, `L`, `C`, `R`, or `F`
- **Index** - Zero-based position within that slot (0, 1, 2, ...)
- **Nesting** - Dash-separated chain for nested layouts

### Region Letters

| Letter | Region | Semantic Role |
|--------|--------|---------------|
| `H` | Header | Top navigation, branding |
| `L` | Left | Primary sidebar, navigation |
| `C` | Content | Main content area |
| `R` | Right | Secondary sidebar, widgets |
| `F` | Footer | Bottom links, copyright |

### ID Examples

**Simple layout:**
```html
<div data-layout="root">
    <header data-slot="H">
        <div data-block="H-0">First header element</div>
        <div data-block="H-1">Second header element</div>
    </header>
    <main data-slot="C">
        <div data-block="C-0">First content element</div>
    </main>
</div>
```

**Nested layout:**
```html
<div data-layout="root">
    <main data-slot="C">
        <div data-block="C-0">
            <!-- Nested layout inside content -->
            <div data-layout="C-0-">
                <aside data-slot="C-0-L">
                    <div data-block="C-0-L-0">Nested left sidebar</div>
                </aside>
                <main data-slot="C-0-C">
                    <div data-block="C-0-C-0">Nested content</div>
                </main>
            </div>
        </div>
    </main>
</div>
```

### ID Interpretation

| ID | Meaning |
|----|---------|
| `H-0` | First element in Header |
| `L-2` | Third element in Left sidebar |
| `C-0` | First element in Content |
| `C-L-0` | Content > Left > First element |
| `C-R-2` | Content > Right > Third element |
| `C-L-0-R-1` | Content > Left > First > Right > Second |
| `H-0-C-0-L-0` | Header > Content > Left (deeply nested) |

### Using IDs for CSS

The ID system enables precise CSS targeting:

```css
/* Target first header element */
[data-block="H-0"] {
    background: #1a1a2e;
}

/* Target all elements in left sidebar */
[data-slot="L"] > [data-block] {
    padding: 1rem;
}

/* Target nested content areas */
[data-block*="-C-"] {
    margin: 2rem;
}

/* Target second element in any right sidebar */
[data-block$="-R-1"] {
    border-top: 1px solid #e5e7eb;
}

/* Target deeply nested layouts */
[data-layout*="-"][data-layout*="-"] {
    background: #f9fafb;
}
```

### Using IDs for Testing

```php
// PHPUnit/Pest
$this->assertSee('[data-block="H-0"]');
$this->assertSeeInOrder(['[data-slot="L"]', '[data-slot="C"]']);

// Playwright/Cypress
await page.locator('[data-block="C-0"]').click();
await expect(page.locator('[data-slot="R"]')).toBeVisible();
```

### Using IDs for JavaScript

```javascript
// Target specific elements
const header = document.querySelector('[data-block="H-0"]');
const sidebar = document.querySelector('[data-slot="L"]');

// Dynamic targeting
function getContentBlock(index) {
    return document.querySelector(`[data-block="C-${index}"]`);
}

// Nested targeting
const nestedLeft = document.querySelector('[data-block="C-L-0"]');
```

## Responsive Design Patterns

### Mobile-First Stacking

On mobile, stack regions vertically:

```blade
<x-hlcrf::layout
    :breakpoints="[
        'mobile' => 'stack',
        'tablet' => 'LC',
        'desktop' => 'LCR',
    ]"
>
    <x-hlcrf::left>Navigation</x-hlcrf::left>
    <x-hlcrf::content>Content</x-hlcrf::content>
    <x-hlcrf::right>Widgets</x-hlcrf::right>
</x-hlcrf::layout>
```

**Behavior:**
- **Mobile (< 768px):** Left -> Content -> Right (vertical)
- **Tablet (768px-1024px):** Left | Content (two columns)
- **Desktop (> 1024px):** Left | Content | Right (three columns)

### Collapsible Sidebars

```blade
<x-hlcrf::left
    collapsible="true"
    collapsed-width="64px"
    expanded-width="256px"
    :collapsed="$sidebarCollapsed"
>
    <div class="sidebar-content">
        @if(!$sidebarCollapsed)
            <span>Full navigation content</span>
        @else
            <span>Icons only</span>
        @endif
    </div>
</x-hlcrf::left>
```

### Hidden Regions on Mobile

```blade
<x-hlcrf::right
    class="hidden md:block"
    width="300px"
>
    {{-- Only visible on medium screens and up --}}
    <x-widget-panel />
</x-hlcrf::right>
```

### Flexible Width Distribution

```blade
<x-hlcrf::layout>
    <x-hlcrf::left width="250px" class="shrink-0">
        Fixed-width sidebar
    </x-hlcrf::left>

    <x-hlcrf::content class="flex-1 min-w-0">
        Flexible content
    </x-hlcrf::content>

    <x-hlcrf::right width="25%" class="shrink-0">
        Percentage-width sidebar
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Responsive Grid Inside Content

```blade
<x-hlcrf::content>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <x-stat-card title="Users" :value="$userCount" />
        <x-stat-card title="Posts" :value="$postCount" />
        <x-stat-card title="Comments" :value="$commentCount" />
    </div>
</x-hlcrf::content>
```

## Complex Real-World Examples

### Admin Dashboard

A complete admin dashboard with nested layouts:

```php
use Core\Front\Components\Layout;

// Main admin layout
$admin = Layout::make('HLCF')
    ->h(
        '<nav class="flex items-center justify-between px-4 py-2 bg-gray-900 text-white">
            <div class="logo">Admin Panel</div>
            <div class="user-menu">
                <span>user@example.com</span>
            </div>
        </nav>'
    )
    ->l(
        '<nav class="w-64 bg-gray-800 text-gray-300 min-h-screen p-4">
            <a href="/dashboard" class="block py-2">Dashboard</a>
            <a href="/users" class="block py-2">Users</a>
            <a href="/settings" class="block py-2">Settings</a>
        </nav>'
    )
    ->c(
        // Nested layout inside content
        Layout::make('HCR')
            ->h('<div class="flex items-center justify-between p-4 border-b">
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <button class="btn-primary">New Item</button>
            </div>')
            ->c('<div class="p-6">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-white p-4 rounded shadow">Stat 1</div>
                    <div class="bg-white p-4 rounded shadow">Stat 2</div>
                    <div class="bg-white p-4 rounded shadow">Stat 3</div>
                </div>
                <div class="bg-white p-4 rounded shadow">
                    <h2 class="font-medium mb-4">Recent Activity</h2>
                    <table class="w-full">...</table>
                </div>
            </div>')
            ->r('<aside class="w-80 p-4 bg-gray-50 border-l">
                <h3 class="font-medium mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <button class="w-full btn-secondary">Export Data</button>
                    <button class="w-full btn-secondary">Generate Report</button>
                </div>
            </aside>')
    )
    ->f(
        '<footer class="px-4 py-2 bg-gray-100 text-gray-600 text-sm">
            Version 1.0.0 | Last sync: 5 minutes ago
        </footer>'
    );

echo $admin->render();
```

**Generated IDs:**
- `H-0` - Admin header/navigation
- `L-0` - Sidebar navigation
- `C-0` - Nested layout container
- `C-0-H-0` - Content header (page title/actions)
- `C-0-C-0` - Content main area (stats/table)
- `C-0-R-0` - Content right sidebar (quick actions)
- `F-0` - Admin footer

### E-Commerce Product Page

Product page with nested sections:

```php
$productPage = Layout::make('HCF')
    ->h('<header class="border-b">
        <nav>Store Navigation</nav>
        <div>Search | Cart | Account</div>
    </header>')
    ->c(
        Layout::make('LCR')
            ->l('<div class="w-1/2">
                <div class="aspect-square bg-gray-100">
                    <img src="/product-main.jpg" alt="Product" />
                </div>
                <div class="flex gap-2 mt-4">
                    <img src="/thumb-1.jpg" class="w-16 h-16" />
                    <img src="/thumb-2.jpg" class="w-16 h-16" />
                </div>
            </div>')
            ->c(
                // Empty - using left/right only
            )
            ->r('<div class="w-1/2 p-6">
                <h1 class="text-2xl font-bold">Product Name</h1>
                <p class="text-xl text-green-600 mt-2">$99.99</p>
                <p class="mt-4">Product description...</p>

                <div class="mt-6 space-y-4">
                    <select>Size options</select>
                    <button class="w-full btn-primary">Add to Cart</button>
                </div>

                <div class="mt-6 border-t pt-4">
                    <h3>Shipping Info</h3>
                    <p>Free delivery over $50</p>
                </div>
            </div>'),
        // Reviews section
        Layout::make('CR')
            ->c('<div class="p-6">
                <h2 class="text-xl font-bold mb-4">Customer Reviews</h2>
                <div class="space-y-4">
                    <div class="border-b pb-4">Review 1...</div>
                    <div class="border-b pb-4">Review 2...</div>
                </div>
            </div>')
            ->r('<aside class="w-64 p-4 bg-gray-50">
                <h3>You May Also Like</h3>
                <div class="space-y-2">
                    <div>Related Product 1</div>
                    <div>Related Product 2</div>
                </div>
            </aside>')
    )
    ->f('<footer class="bg-gray-900 text-white p-8">
        <div class="grid grid-cols-4 gap-8">
            <div>About Us</div>
            <div>Customer Service</div>
            <div>Policies</div>
            <div>Newsletter</div>
        </div>
    </footer>');
```

### Multi-Panel Settings Page

Settings page with multiple nested panels:

```php
$settings = Layout::make('HLC')
    ->h('<header class="border-b p-4">
        <h1>Account Settings</h1>
    </header>')
    ->l('<nav class="w-48 border-r">
        <a href="#profile" class="block p-3 bg-blue-50">Profile</a>
        <a href="#security" class="block p-3">Security</a>
        <a href="#notifications" class="block p-3">Notifications</a>
        <a href="#billing" class="block p-3">Billing</a>
    </nav>')
    ->c(
        // Profile section
        Layout::make('HCF')
            ->h('<div class="p-4 border-b">
                <h2 class="font-semibold">Profile Information</h2>
                <p class="text-gray-600 text-sm">Update your account details</p>
            </div>')
            ->c('<form class="p-6 space-y-4">
                <div>
                    <label>Name</label>
                    <input type="text" value="John Doe" />
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" value="john@example.com" />
                </div>
                <div>
                    <label>Bio</label>
                    <textarea rows="4"></textarea>
                </div>
            </form>')
            ->f('<div class="p-4 border-t bg-gray-50 flex justify-end gap-2">
                <button class="btn-secondary">Cancel</button>
                <button class="btn-primary">Save Changes</button>
            </div>')
    );
```

### Documentation Site

Documentation layout with table of contents:

```php
$docs = Layout::make('HLCRF')
    ->h('<header class="border-b">
        <div class="flex items-center justify-between px-6 py-3">
            <div class="flex items-center gap-4">
                <img src="/logo.svg" class="h-8" />
                <nav class="hidden md:flex gap-6">
                    <a href="/docs">Docs</a>
                    <a href="/api">API</a>
                    <a href="/examples">Examples</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <input type="search" placeholder="Search..." />
                <a href="/github">GitHub</a>
            </div>
        </div>
    </header>')
    ->l('<nav class="w-64 p-4 border-r overflow-y-auto">
        <h4 class="font-semibold text-gray-500 uppercase text-xs mb-2">Getting Started</h4>
        <a href="/docs/intro" class="block py-1 text-blue-600">Introduction</a>
        <a href="/docs/install" class="block py-1">Installation</a>
        <a href="/docs/quick-start" class="block py-1">Quick Start</a>

        <h4 class="font-semibold text-gray-500 uppercase text-xs mt-6 mb-2">Core Concepts</h4>
        <a href="/docs/layouts" class="block py-1">Layouts</a>
        <a href="/docs/components" class="block py-1">Components</a>
        <a href="/docs/routing" class="block py-1">Routing</a>
    </nav>')
    ->c('<article class="prose max-w-3xl mx-auto p-8">
        <h1>Introduction</h1>
        <p>Welcome to the documentation...</p>

        <h2>Key Features</h2>
        <ul>
            <li>Feature 1</li>
            <li>Feature 2</li>
            <li>Feature 3</li>
        </ul>

        <h2>Next Steps</h2>
        <p>Continue to the installation guide...</p>
    </article>')
    ->r('<aside class="w-48 p-4 border-l">
        <h4 class="font-semibold text-sm mb-2">On This Page</h4>
        <nav class="text-sm space-y-1">
            <a href="#intro" class="block text-gray-600">Introduction</a>
            <a href="#features" class="block text-gray-600">Key Features</a>
            <a href="#next" class="block text-gray-600">Next Steps</a>
        </nav>
    </aside>')
    ->f('<footer class="border-t p-4 flex justify-between text-sm text-gray-600">
        <div>
            <a href="/prev" class="text-blue-600">&larr; Previous: Setup</a>
        </div>
        <div>
            <a href="/next" class="text-blue-600">Next: Installation &rarr;</a>
        </div>
    </footer>');
```

### Email Client Interface

Complex email client with multiple nested panels:

```php
$email = Layout::make('HLCR')
    ->h('<header class="bg-white border-b px-4 py-2 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button class="btn-icon">Menu</button>
            <img src="/logo.svg" class="h-6" />
        </div>
        <div class="flex-1 max-w-2xl mx-4">
            <input type="search" placeholder="Search mail" class="w-full" />
        </div>
        <div class="flex items-center gap-2">
            <button class="btn-icon">Settings</button>
            <div class="avatar">JD</div>
        </div>
    </header>')
    ->l('<aside class="w-64 border-r flex flex-col">
        <div class="p-3">
            <button class="w-full btn-primary">Compose</button>
        </div>
        <nav class="flex-1 overflow-y-auto">
            <a href="#inbox" class="flex items-center gap-3 px-4 py-2 bg-blue-50">
                <span class="icon">inbox</span>
                <span class="flex-1">Inbox</span>
                <span class="badge">12</span>
            </a>
            <a href="#starred" class="flex items-center gap-3 px-4 py-2">
                <span class="icon">star</span>
                <span>Starred</span>
            </a>
            <a href="#sent" class="flex items-center gap-3 px-4 py-2">
                <span class="icon">send</span>
                <span>Sent</span>
            </a>
            <a href="#drafts" class="flex items-center gap-3 px-4 py-2">
                <span class="icon">draft</span>
                <span>Drafts</span>
            </a>
        </nav>
        <div class="p-4 border-t text-sm text-gray-600">
            Storage: 2.4 GB / 15 GB
        </div>
    </aside>')
    ->c(
        Layout::make('LC')
            ->l('<div class="w-80 border-r overflow-y-auto">
                <div class="p-2 border-b">
                    <select class="w-full text-sm">
                        <option>All Mail</option>
                        <option>Unread</option>
                        <option>Starred</option>
                    </select>
                </div>
                <div class="divide-y">
                    <div class="p-3 bg-blue-50 cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">John Smith</span>
                            <span class="text-xs text-gray-500">10:30 AM</span>
                        </div>
                        <div class="font-medium text-sm">Meeting Tomorrow</div>
                        <div class="text-sm text-gray-600 truncate">Hi, just wanted to confirm...</div>
                    </div>
                    <div class="p-3 cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Jane Doe</span>
                            <span class="text-xs text-gray-500">Yesterday</span>
                        </div>
                        <div class="font-medium text-sm">Project Update</div>
                        <div class="text-sm text-gray-600 truncate">Here is the latest update...</div>
                    </div>
                </div>
            </div>')
            ->c('<div class="flex-1 flex flex-col">
                <div class="p-4 border-b flex items-center gap-2">
                    <button class="btn-icon">Archive</button>
                    <button class="btn-icon">Delete</button>
                    <button class="btn-icon">Move</button>
                    <span class="border-l h-6 mx-2"></span>
                    <button class="btn-icon">Reply</button>
                    <button class="btn-icon">Forward</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <div class="mb-6">
                        <h2 class="text-xl font-medium">Meeting Tomorrow</h2>
                        <div class="flex items-center gap-3 mt-2 text-sm text-gray-600">
                            <div class="avatar">JS</div>
                            <div>
                                <div>John Smith &lt;john@example.com&gt;</div>
                                <div>to me</div>
                            </div>
                            <div class="ml-auto">Jan 15, 2026, 10:30 AM</div>
                        </div>
                    </div>
                    <div class="prose">
                        <p>Hi,</p>
                        <p>Just wanted to confirm our meeting tomorrow at 2pm.</p>
                        <p>Best regards,<br>John</p>
                    </div>
                </div>
            </div>')
    )
    ->r('<aside class="w-64 border-l p-4 hidden xl:block">
        <h3 class="font-medium mb-4">Contact Info</h3>
        <div class="text-sm space-y-2">
            <div>John Smith</div>
            <div class="text-gray-600">john@example.com</div>
            <div class="text-gray-600">+1 555 123 4567</div>
        </div>
        <h3 class="font-medium mt-6 mb-4">Related Emails</h3>
        <div class="text-sm space-y-2">
            <a href="#" class="block text-blue-600">Re: Project Timeline</a>
            <a href="#" class="block text-blue-600">Meeting Notes</a>
        </div>
    </aside>');
```

## Performance Considerations

### Lazy Content Loading

For large layouts, defer non-critical content:

```php
$layout = Layout::make('LCR')
    ->l('<nav>Immediate navigation</nav>')
    ->c('<main wire:init="loadContent">
        <div wire:loading>Loading...</div>
        <div wire:loading.remove>@livewire("content-panel")</div>
    </main>')
    ->r(fn () => view('widgets.sidebar')); // Closure defers evaluation
```

### Conditional Region Rendering

Only render regions when needed:

```php
$layout = Layout::make('LCR');

$layout->l('<nav>Navigation</nav>');
$layout->c('<main>Content</main>');

// Conditionally add right sidebar
if ($user->hasFeature('widgets')) {
    $layout->r('<aside>Widgets</aside>');
}
```

### Efficient CSS Targeting

Use data attributes instead of deep selectors:

```css
/* Efficient - uses data attribute */
[data-block="C-0"] { padding: 1rem; }

/* Less efficient - deep selector */
.hlcrf-layout > .hlcrf-body > .hlcrf-content > div:first-child { padding: 1rem; }
```

## Testing HLCRF Layouts

### Unit Testing

```php
use Core\Front\Components\Layout;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase
{
    public function test_generates_correct_ids(): void
    {
        $layout = Layout::make('LC')
            ->l('Left')
            ->c('Content');

        $html = $layout->render();

        $this->assertStringContainsString('data-slot="L"', $html);
        $this->assertStringContainsString('data-slot="C"', $html);
        $this->assertStringContainsString('data-block="L-0"', $html);
        $this->assertStringContainsString('data-block="C-0"', $html);
    }

    public function test_nested_layout_ids(): void
    {
        $nested = Layout::make('LR')
            ->l('Nested Left')
            ->r('Nested Right');

        $outer = Layout::make('C')
            ->c($nested);

        $html = $outer->render();

        $this->assertStringContainsString('data-block="C-0-L-0"', $html);
        $this->assertStringContainsString('data-block="C-0-R-0"', $html);
    }
}
```

### Browser Testing

```php
// Pest with Playwright
it('renders admin layout correctly', function () {
    $this->browse(function ($browser) {
        $browser->visit('/admin')
            ->assertPresent('[data-layout="root"]')
            ->assertPresent('[data-slot="H"]')
            ->assertPresent('[data-slot="L"]')
            ->assertPresent('[data-slot="C"]');
    });
});
```

## Best Practices

### 1. Use Semantic Region Names

```php
// Good - semantic use
->h('<nav>Global navigation</nav>')
->l('<nav>Page navigation</nav>')
->c('<main>Page content</main>')
->r('<aside>Related content</aside>')
->f('<footer>Site footer</footer>')

// Bad - misuse of regions
->h('<aside>Sidebar content</aside>')  // Header for sidebar?
```

### 2. Leverage the ID System

```css
/* Target specific elements precisely */
[data-block="H-0"] { /* Header first element */ }
[data-block="C-L-0"] { /* Content > Left > First */ }

/* Don't fight the system with complex selectors */
```

### 3. Keep Nesting Shallow

```php
// Good - 2-3 levels max
Layout::make('HCF')
    ->c(Layout::make('LCR')->...);

// Avoid - too deep
Layout::make('C')
    ->c(Layout::make('C')
        ->c(Layout::make('C')
            ->c(Layout::make('C')...))));
```

### 4. Use Consistent Widths

```php
// Good - consistent sidebar widths across app
->l('<nav class="w-64">')  // Always 256px
->r('<aside class="w-80">') // Always 320px
```

### 5. Handle Empty Regions Gracefully

```php
// Regions without content don't render
$layout = Layout::make('LCR')
    ->l('<nav>Nav</nav>')
    ->c('<main>Content</main>');
    // No ->r() call - right sidebar won't render
```

## Learn More

- [HLCRF Pattern Overview](/patterns-guide/hlcrf)
- [Form Components](/packages/admin/forms)
- [Livewire Modals](/packages/admin/modals)
- [Creating Admin Panels](/packages/admin/creating-admin-panels)
