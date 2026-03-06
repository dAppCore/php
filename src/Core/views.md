# Core Architecture: The Core View Protocol (Modern Flexy)
# =========================================================
* **A strict architectural guideline for implementing Server-Side MVVM in Laravel.**
* **Part of the Core PHP Framework.**

## **Philosophy**

1. **The Controller is a Traffic Cop:** It accepts input, delegates to the Domain, and selects a View Modal. It never touches HTML or data formatting.
2. **The View Modal is the Interface:** All presentation logic, formatting, state management, and data preparation happen here. This is the "ViewModel" (implemented as Livewire Components).
3. **The Template is Dumb:** The template file (.blade.php) contains ONLY HTML and simple variable interpolation. No complex logic, no database calls, no calculations.

## **Directory Structure**
Inside a Core Module (`app/Mod/{Name}/`):

```text
View/
├── Modal/                 # The View Modals (Livewire Components)
│   ├── Admin/             # (Namespace: Mod\{Mod}\View\Modal\Admin)
│   │   └── {Resource}Manager.php
│   ├── Web/               # (Namespace: Mod\{Mod}\View\Modal\Web)
│   │   └── {Resource}Page.php
│   └── {Resource}Page.php
└── Blade/                 # The Dumb HTML Templates
    ├── admin/             # (View Namespace: {mod}::admin)
    │   └── {resource}-manager.blade.php
    └── web/               # (View Namespace: {mod}::web)
        └── {resource}-page.blade.php
```

## **Implementation Guide**

### **1. The Controller (Optional with Livewire)**

**Role:** Accept request, fetch raw Domain Entity (if needed), return View Modal.
**Constraint:** Must return an instance of a View Modal or render it via Livewire route binding.

```php
// Example: InvoiceController.php
namespace Mod\Billing\Controllers\Web;

use Mod\Billing\View\Modal\InvoicePage;

class InvoiceController
{
    public function show(int $id)
    {
        // Delegate directly to the View Modal
        // Livewire handles the hydration
        return new InvoicePage($id);
    }
}
```

### **2. The View Modal (The "Flexy" Layer)**

**Role:** Prepare data for display. Format currencies, dates, handle conditional UI logic, and manage state.
**Constraint:** Public properties/methods correspond 1:1 with what the template needs.

```php
// Example: InvoicePage.php
namespace Mod\Billing\View\Modal;

use Livewire\Component;
use Mod\Billing\Models\Invoice;

class InvoicePage extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    // Logic lives HERE, not in the HTML.
    public function getInvoiceIdProperty(): string
    {
        return '#' . str_pad($this->invoice->id, 6, '0', STR_PAD_LEFT);
    }

    public function getFormattedTotalProperty(): string
    {
        return number_format($this->invoice->total / 100, 2);
    }

    public function getIsOverdueProperty(): bool
    {
        return $this->invoice->due_date->isPast() && !$this->invoice->paid;
    }

    public function render()
    {
        // Points to the dumb template in the module
        // Structure: app/Mod/Billing/View/Blade/invoice-page.blade.php
        return view('billing::invoice-page');
    }
}
```

### **3. The Template (The Dumb Layer)**

**Role:** Layout and structure only.
**Constraint:** No method calls with arguments. No `@php` blocks. No complex `@if` conditions (use boolean getters from the View Modal instead).

```bladehtml
<!-- invoice-page.blade.php -->
<div class="invoice-container">
    <h1>Invoice {{ $this->invoiceId }}</h1>

    <div class="status">
        <!-- Logic is hidden behind the boolean getter -->
        @if($this->isOverdue)
            <span class="badge-danger">Overdue</span>
        @else
            <span class="badge-success">Active</span>
        @endif
    </div>

    <div class="amount">
        Total: ${{ $this->formattedTotal }}
    </div>
</div>
```

## **Enforcement Rules**

1. **No Logic in Blade:** Regex check for logic operators (`&&`, `||`, `>`) inside `{{ }}` tags in .blade.php files.
2. **No Eloquent in Blade:** The template should interact with the View Modal's getters/properties, not the raw Eloquent model relationships.
3. **Strict Typing:** View Modals must strictly type their dependencies.
