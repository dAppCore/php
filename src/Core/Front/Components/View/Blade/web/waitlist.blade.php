@php
    $meta = [
        'title' => ($workspace?->name ?? 'Host UK') . ' - Coming Soon',
        'description' => $workspace?->description ?? 'I\'m working on something amazing. Join the waitlist to be notified when it launches.',
        'url' => request()->url(),
    ];
@endphp

<x-satellite.layout :workspace="$workspace" :meta="$meta">

    <section class="min-h-[80vh] flex items-center justify-center">
        <div class="px-4 sm:px-6 py-12 md:py-20">
            <div class="max-w-3xl mx-auto text-center">

                <!-- Coming Soon Badge with decorative lines -->
                <div class="relative flex items-center justify-center gap-4 mb-8">
                    <div class="h-px w-16 sm:w-24 bg-gradient-to-l from-violet-500/50 to-transparent"></div>
                    <div class="relative inline-flex items-center gap-2 px-4 py-2 rounded-full bg-violet-500/10 border border-violet-500/20">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                        </span>
                        <span class="text-sm font-medium text-violet-300">Coming Soon</span>
                    </div>
                    <div class="h-px w-16 sm:w-24 bg-gradient-to-r from-violet-500/50 to-transparent"></div>
                </div>

                <!-- Logo/Icon -->
                @if($workspace?->icon)
                    <div class="w-20 h-20 mx-auto mb-8 rounded-2xl bg-{{ $workspace->color ?? 'violet' }}-500/20 flex items-center justify-center">
                        <i class="fa-solid fa-{{ $workspace->icon }} text-4xl text-{{ $workspace->color ?? 'violet' }}-400"></i>
                    </div>
                @else
                    <div class="w-20 h-20 mx-auto mb-8">
                        <img src="/images/host-uk-raven.svg" alt="Host UK" class="w-full h-full">
                    </div>
                @endif

                <!-- Title -->
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6">
                    <span class="bg-gradient-to-b from-slate-100 to-slate-400 bg-clip-text text-transparent">
                        {{ $workspace?->name ?? 'Something Amazing' }}
                    </span>
                </h1>

                <!-- Description -->
                <p class="text-lg md:text-xl text-slate-400 mb-10 max-w-xl mx-auto">
                    {{ $workspace?->description ?? 'I\'m working on something special. Join the waitlist to be the first to know when it launches.' }}
                </p>

                <!-- Waitlist Form -->
                @if($subscribed)
                    <div class="inline-flex items-center gap-3 px-6 py-4 rounded-xl bg-green-500/10 border border-green-500/20">
                        <i class="fa-solid fa-check-circle text-green-400 text-xl"></i>
                        <span class="text-green-300">You're on the list! I'll notify you when it launches.</span>
                    </div>
                @else
                    <!-- Form with decorative gradient lines -->
                    <div class="relative flex items-center justify-center gap-10 mb-4">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
                    </div>

                    <form action="/subscribe" method="POST" class="relative max-w-md mx-auto">
                        @csrf

                        <!-- Corner dots decoration -->
                        <div class="absolute -inset-3 bg-violet-500/5 rounded-2xl -z-10 before:absolute before:inset-y-0 before:left-0 before:w-[10px] before:bg-[length:10px_10px] before:[background-position:top_center,bottom_center] before:bg-no-repeat before:[background-image:radial-gradient(circle_at_center,rgb(139_92_246_/_0.4)_1.5px,transparent_1.5px),radial-gradient(circle_at_center,rgb(139_92_246_/_0.4)_1.5px,transparent_1.5px)] after:absolute after:inset-y-0 after:right-0 after:w-[10px] after:bg-[length:10px_10px] after:[background-position:top_center,bottom_center] after:bg-no-repeat after:[background-image:radial-gradient(circle_at_center,rgb(139_92_246_/_0.4)_1.5px,transparent_1.5px),radial-gradient(circle_at_center,rgb(139_92_246_/_0.4)_1.5px,transparent_1.5px)]" aria-hidden="true"></div>

                        <div class="relative p-1 rounded-xl bg-gradient-to-r from-violet-500/20 via-purple-500/20 to-violet-500/20">
                            <div class="flex gap-2 p-1 rounded-lg bg-slate-900">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-envelope text-slate-500"></i>
                                    </div>
                                    <input
                                        type="email"
                                        name="email"
                                        placeholder="Enter your email..."
                                        required
                                        class="w-full pl-11 pr-4 py-3 bg-slate-800/50 border border-slate-700/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition"
                                    >
                                </div>
                                <button
                                    type="submit"
                                    class="px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-lg transition whitespace-nowrap"
                                >
                                    Join Waitlist
                                </button>
                            </div>
                        </div>

                        @error('email')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </form>

                    <div class="relative flex items-center justify-center gap-10 mt-4">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
                    </div>
                @endif

                <!-- Social Proof -->
                <div class="mt-12 flex flex-col items-center gap-4">
                    <div class="flex -space-x-2">
                        @for($i = 1; $i <= 5; $i++)
                            <div class="w-8 h-8 rounded-full bg-slate-700 border-2 border-slate-900 flex items-center justify-center">
                                <i class="fa-solid fa-user text-slate-500 text-xs"></i>
                            </div>
                        @endfor
                    </div>
                    <p class="text-sm text-slate-500">
                        Join <span class="text-slate-300 font-medium">hundreds</span> of others waiting for launch
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- Features Preview -->
    <section class="relative">
        <!-- Gradient border top -->
        <div class="h-px w-full bg-gradient-to-r from-transparent via-slate-700/50 to-transparent"></div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-16">
            <h2 class="text-xl font-semibold text-center text-slate-300 mb-12">What to expect</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center p-6 rounded-xl bg-slate-800/30 border border-slate-700/50">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-violet-500/20 flex items-center justify-center">
                        <i class="fa-solid fa-rocket text-violet-400"></i>
                    </div>
                    <h3 class="font-semibold text-slate-200 mb-2">Fast Performance</h3>
                    <p class="text-sm text-slate-400">Blazing fast load times with edge caching across 96+ locations worldwide.</p>
                </div>
                <div class="text-center p-6 rounded-xl bg-slate-800/30 border border-slate-700/50">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-purple-500/20 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved text-purple-400"></i>
                    </div>
                    <h3 class="font-semibold text-slate-200 mb-2">Secure & Private</h3>
                    <p class="text-sm text-slate-400">GDPR compliant with EU-hosted infrastructure. Your data stays safe.</p>
                </div>
                <div class="text-center p-6 rounded-xl bg-slate-800/30 border border-slate-700/50">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-pink-500/20 flex items-center justify-center">
                        <i class="fa-solid fa-heart text-pink-400"></i>
                    </div>
                    <h3 class="font-semibold text-slate-200 mb-2">Creator Friendly</h3>
                    <p class="text-sm text-slate-400">Built for content creators who need reliable, adult-friendly hosting.</p>
                </div>
            </div>
        </div>
    </section>

</x-satellite.layout>
