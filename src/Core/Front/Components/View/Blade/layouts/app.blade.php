@props([
    'title' => null,
])

{{--
Marketing/Sales Layout (app.blade.php)
Use for: Landing pages, pricing, about, services - anything with particle animation and sales focus

Other layouts available:
- content.blade.php    → Blog posts, guides, legal pages (centred prose)
- sidebar-left.blade.php → Help centre, FAQ, documentation (left nav + content)
- sidebar-right.blade.php → Long guides with TOC (content + right sidebar)
- focused.blade.php    → Checkout, forms, onboarding (minimal, focused)
--}}

<x-layouts::partials.base :title="$title" :particles="true">
    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <x-layouts::partials.header />

        <!-- Main Content -->
        <main id="main-content" class="grow pt-16 md:pt-20 flex flex-col">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 md:py-12 w-full flex-1 flex flex-col justify-center">
                {{ $slot }}
            </div>
        </main>

        <x-layouts::partials.footer />

    </div>
</x-layouts::partials.base>
