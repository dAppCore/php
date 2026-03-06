{{--
Example: Help Centre using Sidebar Left Layout
Route: /examples/help-centre
--}}

<x-layouts.sidebar-left
    title="Help Centre"
    description="Find answers to common questions or get in touch with the support team.">

    <x-slot:sidebar>
        <x-sidebar-nav-item href="/help" icon="fa-circle-question" active>
            General
        </x-sidebar-nav-item>
        <x-sidebar-nav-item href="/help/billing" icon="fa-credit-card">
            Billing
        </x-sidebar-nav-item>
        <x-sidebar-nav-item href="/help/account" icon="fa-user">
            Account
        </x-sidebar-nav-item>
        <x-sidebar-nav-item href="/help/host-social" icon="fa-share-nodes" badge="New">
            Host Social
        </x-sidebar-nav-item>
        <x-sidebar-nav-item href="/help/host-link" icon="fa-link">
            Host Link
        </x-sidebar-nav-item>
        <x-sidebar-nav-item href="/help/api" icon="fa-code">
            API
        </x-sidebar-nav-item>
    </x-slot:sidebar>

    <!-- FAQ Content -->
    <div class="space-y-6">
        <div class="stellar-card p-6">
            <h2 class="text-xl font-bold text-white mb-6">Frequently Asked Questions</h2>

            <div class="space-y-4" x-data="{ open: null }">
                <!-- FAQ Item 1 -->
                <div class="border-b border-slate-700 pb-4">
                    <button @click="open = open === 1 ? null : 1"
                            class="flex items-center justify-between w-full text-left">
                        <span class="font-medium text-white">How do I get started with Host UK?</span>
                        <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                           :class="{ 'rotate-180': open === 1 }"></i>
                    </button>
                    <div x-show="open === 1" x-collapse class="mt-3 text-slate-400">
                        <p>Getting started is simple. Create an account, verify your email, and you'll have immediate access to your Host Hub dashboard. From there, you can set up your bio pages, connect your social accounts, and start scheduling content.</p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="border-b border-slate-700 pb-4">
                    <button @click="open = open === 2 ? null : 2"
                            class="flex items-center justify-between w-full text-left">
                        <span class="font-medium text-white">What payment methods do you accept?</span>
                        <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                           :class="{ 'rotate-180': open === 2 }"></i>
                    </button>
                    <div x-show="open === 2" x-collapse class="mt-3 text-slate-400">
                        <p>Host UK accepts all major credit and debit cards (Visa, Mastercard, American Express) as well as PayPal. All payments are processed securely through Stripe.</p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="border-b border-slate-700 pb-4">
                    <button @click="open = open === 3 ? null : 3"
                            class="flex items-center justify-between w-full text-left">
                        <span class="font-medium text-white">Can I cancel my subscription at any time?</span>
                        <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                           :class="{ 'rotate-180': open === 3 }"></i>
                    </button>
                    <div x-show="open === 3" x-collapse class="mt-3 text-slate-400">
                        <p>Yes, you can cancel your subscription at any time from your account settings. Your access will continue until the end of your current billing period, and you won't be charged again.</p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="border-b border-slate-700 pb-4">
                    <button @click="open = open === 4 ? null : 4"
                            class="flex items-center justify-between w-full text-left">
                        <span class="font-medium text-white">Do you offer refunds?</span>
                        <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                           :class="{ 'rotate-180': open === 4 }"></i>
                    </button>
                    <div x-show="open === 4" x-collapse class="mt-3 text-slate-400">
                        <p>Host UK offers a 14-day money-back guarantee for new subscriptions. If you're not satisfied within the first 14 days, contact the support team for a full refund.</p>
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="pb-4">
                    <button @click="open = open === 5 ? null : 5"
                            class="flex items-center justify-between w-full text-left">
                        <span class="font-medium text-white">How do I connect my social media accounts?</span>
                        <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                           :class="{ 'rotate-180': open === 5 }"></i>
                    </button>
                    <div x-show="open === 5" x-collapse class="mt-3 text-slate-400">
                        <p>Navigate to Host Social in your dashboard and click "Connect Account". You'll be guided through the OAuth process for each platform. Host Social supports Instagram, Twitter/X, Facebook, LinkedIn, TikTok, and YouTube.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support Card -->
        <div class="stellar-card p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-500/20 text-purple-400 mb-4">
                <i class="fa-solid fa-headset text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Still need help?</h3>
            <p class="text-slate-400 mb-4">The support team is available Monday to Friday, 9am to 6pm GMT.</p>
            <a href="/contact" class="btn-sm text-white bg-purple-600 hover:bg-purple-700">
                Contact Support
            </a>
        </div>
    </div>

</x-layouts.sidebar-left>
