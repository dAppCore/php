{{-- HLCRF Layout Test Page --}}
{{-- Tests: HCF, HLCF, HLCRF, LCR, CF variants --}}

<div class="space-y-8 p-8">

    {{-- Test 0: Two layouts side by side - slot isolation test --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 0: Slot Isolation (Two Layouts Side by Side)</h2>
        <div class="grid grid-cols-2 gap-4">
            <core:layout variant="HCF" class="border border-red-300 min-h-32">
                <x-slot:header>
                    <div class="bg-red-100 p-2">Layout A Header</div>
                </x-slot:header>
                <div class="bg-red-50 p-2">Layout A Content</div>
                <x-slot:footer>
                    <div class="bg-red-100 p-2">Layout A Footer</div>
                </x-slot:footer>
            </core:layout>

            <core:layout variant="HCF" class="border border-blue-300 min-h-32">
                <x-slot:header>
                    <div class="bg-blue-100 p-2">Layout B Header</div>
                </x-slot:header>
                <div class="bg-blue-50 p-2">Layout B Content</div>
                <x-slot:footer>
                    <div class="bg-blue-100 p-2">Layout B Footer</div>
                </x-slot:footer>
            </core:layout>
        </div>
    </section>

    {{-- Test 1: HCF (Header, Content, Footer) - most common --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 1: HCF (Default)</h2>
        <core:layout variant="HCF" class="border border-gray-300 min-h-48">
            <x-slot:header>
                <div class="bg-blue-100 p-4">Header</div>
            </x-slot:header>

            <div class="bg-green-100 p-4">Content (default slot)</div>

            <x-slot:footer>
                <div class="bg-yellow-100 p-4">Footer</div>
            </x-slot:footer>
        </core:layout>
    </section>

    {{-- Test 2: HLCF (Header, Left, Content, Footer) - admin dashboards --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 2: HLCF (Admin Dashboard)</h2>
        <core:layout variant="HLCF" class="border border-gray-300 min-h-48">
            <x-slot:header>
                <div class="bg-blue-100 p-4">Header</div>
            </x-slot:header>

            <x-slot:left>
                <div class="bg-purple-100 p-4 w-48">Left Sidebar</div>
            </x-slot:left>

            <div class="bg-green-100 p-4">Main Content</div>

            <x-slot:footer>
                <div class="bg-yellow-100 p-4">Footer</div>
            </x-slot:footer>
        </core:layout>
    </section>

    {{-- Test 3: HLCRF (Full layout) --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 3: HLCRF (Full)</h2>
        <core:layout variant="HLCRF" class="border border-gray-300 min-h-48">
            <x-slot:header>
                <div class="bg-blue-100 p-4">Header</div>
            </x-slot:header>

            <x-slot:left>
                <div class="bg-purple-100 p-4 w-32">Left</div>
            </x-slot:left>

            <div class="bg-green-100 p-4">Content</div>

            <x-slot:right>
                <div class="bg-pink-100 p-4 w-32">Right</div>
            </x-slot:right>

            <x-slot:footer>
                <div class="bg-yellow-100 p-4">Footer</div>
            </x-slot:footer>
        </core:layout>
    </section>

    {{-- Test 4: LCR (No header/footer - app body) --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 4: LCR (App Body)</h2>
        <core:layout variant="LCR" class="border border-gray-300 min-h-32">
            <x-slot:left>
                <div class="bg-purple-100 p-4 w-32">Nav</div>
            </x-slot:left>

            <div class="bg-green-100 p-4">Content</div>

            <x-slot:right>
                <div class="bg-pink-100 p-4 w-32">Aside</div>
            </x-slot:right>
        </core:layout>
    </section>

    {{-- Test 5: C (Content only - minimal) --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 5: C (Content Only)</h2>
        <core:layout variant="C" class="border border-gray-300 min-h-24">
            <div class="bg-green-100 p-4">Just content, nothing else</div>
        </core:layout>
    </section>

    {{-- Test 6: Nested HLCRF --}}
    <section>
        <h2 class="text-lg font-bold mb-2">Test 6: Nested (LCR inside HCF)</h2>
        <core:layout variant="HCF" class="border border-gray-300 min-h-48">
            <x-slot:header>
                <div class="bg-blue-100 p-4">App Header</div>
            </x-slot:header>

            {{-- Nested layout in content --}}
            <core:layout variant="LCR" class="h-full">
                <x-slot:left>
                    <div class="bg-purple-100 p-4 w-40 h-full">Sidebar</div>
                </x-slot:left>

                <div class="bg-green-100 p-4">Nested Content</div>

                <x-slot:right>
                    <div class="bg-pink-100 p-4 w-40 h-full">Panel</div>
                </x-slot:right>
            </core:layout>

            <x-slot:footer>
                <div class="bg-yellow-100 p-4">App Footer</div>
            </x-slot:footer>
        </core:layout>
    </section>

</div>
