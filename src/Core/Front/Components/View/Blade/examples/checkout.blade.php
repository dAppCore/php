{{--
Example: Checkout Form using Focused Layout
Route: /examples/checkout
--}}

<x-layouts.focused
    title="Complete your order"
    description="You're one step away from supercharging your social media."
    :step="2"
    :totalSteps="3"
    :showProgress="true">

    <form action="#" method="POST" class="space-y-6">
        @csrf

        <!-- Plan Summary -->
        <div class="flex items-center justify-between p-4 rounded-lg bg-slate-800/50 border border-slate-700">
            <div>
                <div class="font-medium text-white">Creator Pro</div>
                <div class="text-sm text-slate-400">Monthly billing</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-white">£29</div>
                <div class="text-sm text-slate-400">/month</div>
            </div>
        </div>

        <!-- Billing Details -->
        <div class="space-y-4">
            <h3 class="font-medium text-white">Billing details</h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm text-slate-400 mb-1">First name</label>
                    <input type="text" id="first_name" name="first_name"
                           class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                    <label for="last_name" class="block text-sm text-slate-400 mb-1">Last name</label>
                    <input type="text" id="last_name" name="last_name"
                           class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm text-slate-400 mb-1">Email address</label>
                <input type="email" id="email" name="email"
                       class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
            </div>

            <div>
                <label for="company" class="block text-sm text-slate-400 mb-1">Company name <span class="text-slate-500">(optional)</span></label>
                <input type="text" id="company" name="company"
                       class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
            </div>
        </div>

        <!-- Payment Details -->
        <div class="space-y-4">
            <h3 class="font-medium text-white">Payment method</h3>

            <div>
                <label for="card" class="block text-sm text-slate-400 mb-1">Card number</label>
                <div class="relative">
                    <input type="text" id="card" name="card" placeholder="1234 5678 9012 3456"
                           class="w-full px-4 py-2 pr-12 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 flex gap-1">
                        <i class="fa-brands fa-cc-visa text-slate-400"></i>
                        <i class="fa-brands fa-cc-mastercard text-slate-400"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="expiry" class="block text-sm text-slate-400 mb-1">Expiry date</label>
                    <input type="text" id="expiry" name="expiry" placeholder="MM / YY"
                           class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                    <label for="cvc" class="block text-sm text-slate-400 mb-1">CVC</label>
                    <input type="text" id="cvc" name="cvc" placeholder="123"
                           class="w-full px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="flex items-start gap-3">
            <input type="checkbox" id="terms" name="terms"
                   class="mt-1 rounded border-slate-700 bg-slate-800 text-purple-500 focus:ring-purple-500">
            <label for="terms" class="text-sm text-slate-400">
                I agree to the <a href="/terms" class="text-purple-400 hover:underline">Terms of Service</a>
                and <a href="/privacy" class="text-purple-400 hover:underline">Privacy Policy</a>
            </label>
        </div>

        <!-- Submit -->
        <button type="submit"
                class="w-full py-3 px-4 rounded-lg font-medium text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 transition-all">
            Complete Purchase
        </button>

        <!-- Security Note -->
        <div class="flex items-center justify-center gap-2 text-xs text-slate-500">
            <i class="fa-solid fa-lock"></i>
            <span>Secured by Stripe. Your payment info is encrypted.</span>
        </div>
    </form>

    <x-slot:helper>
        <p>Have a promo code? <a href="#" class="text-purple-400 hover:underline">Apply it here</a></p>
    </x-slot:helper>

</x-layouts.focused>
