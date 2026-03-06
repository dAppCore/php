@php
    $appName = config('core.app.name', __('core::core.brand.name'));
@endphp

<div>
    <!-- Hero -->
    <section class="relative pb-20 pt-8">
        <x-hero-glow color="green" />

        <div class="max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/10 border border-green-500/20 text-green-400 text-sm font-medium mb-6">
                <i class="fa-solid fa-tree" aria-hidden="true"></i>
                <span>{{ __('trees::trees.hero.badge') }}</span>
            </div>

            <h1 class="text-5xl md:text-6xl font-extrabold mb-6">
                <span class="text-green-400">{{ number_format($stats['total_trees']) }}</span>
                <span class="text-slate-200">{{ __('trees::trees.hero.trees_planted') }}</span>
            </h1>

            <p class="text-xl text-slate-300 max-w-2xl mx-auto mb-8">
                {{ __('trees::trees.hero.description', ['name' => $appName]) }}
            </p>

            <div class="flex flex-wrap gap-4 justify-center mb-12">
                <a href="#leaderboard" class="btn text-slate-900 bg-gradient-to-r from-white/80 via-white to-white/80 hover:bg-white transition group">
                    {{ __('trees::trees.hero.view_leaderboard') }}
                    <i class="fa-solid fa-arrow-down ml-2 text-sm text-green-500 group-hover:translate-y-0.5 transition-transform" aria-hidden="true"></i>
                </a>
                <a href="#for-agents" class="btn text-slate-200 hover:text-white bg-slate-800 hover:bg-slate-700 transition">
                    {{ __('trees::trees.hero.for_agents') }}
                    <i class="fa-solid fa-robot ml-2 text-xs" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Live Counters -->
    <section class="pb-20">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
            <div class="stellar-card p-6 text-center">
                <div class="text-3xl md:text-4xl font-extrabold text-green-400 mb-2">
                    {{ number_format($stats['trees_this_month']) }}
                </div>
                <div class="text-sm text-slate-400">{{ __('trees::trees.stats.this_month') }}</div>
            </div>
            <div class="stellar-card p-6 text-center">
                <div class="text-3xl md:text-4xl font-extrabold text-green-400 mb-2">
                    {{ number_format($stats['trees_this_year']) }}
                </div>
                <div class="text-sm text-slate-400">{{ __('trees::trees.stats.this_year') }}</div>
            </div>
            <div class="stellar-card p-6 text-center">
                <div class="text-3xl md:text-4xl font-extrabold text-amber-400 mb-2">
                    {{ number_format($stats['total_referrals']) }}
                </div>
                <div class="text-sm text-slate-400">{{ __('trees::trees.stats.total_referrals') }}</div>
            </div>
            <div class="stellar-card p-6 text-center">
                <div class="text-3xl md:text-4xl font-extrabold text-slate-400 mb-2">
                    {{ number_format($stats['queued_trees']) }}
                </div>
                <div class="text-sm text-slate-400">{{ __('trees::trees.stats.in_queue') }}</div>
            </div>
        </div>
    </section>

    <!-- Provider Leaderboard -->
    <section id="leaderboard" class="pt-24 pb-20">
        <div class="text-center mb-16">
            <h2 class="h2 gradient-text pb-4">{{ __('trees::trees.leaderboard.title') }}</h2>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                {{ __('trees::trees.leaderboard.description') }}
            </p>
        </div>

        @if($leaderboard->count() > 0)
            <div class="max-w-3xl mx-auto">
                <div class="stellar-card overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-800">
                                <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">{{ __('trees::trees.leaderboard.table.rank') }}</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">{{ __('trees::trees.leaderboard.table.provider') }}</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-slate-300">{{ __('trees::trees.leaderboard.table.signups') }}</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-slate-300">{{ __('trees::trees.leaderboard.table.trees') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaderboard as $index => $entry)
                                <tr class="border-b border-slate-800/50 last:border-0 hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        @if($index === 0)
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 font-bold">1</span>
                                        @elseif($index === 1)
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-400/20 text-slate-300 font-bold">2</span>
                                        @elseif($index === 2)
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-700/20 text-orange-400 font-bold">3</span>
                                        @else
                                            <span class="text-slate-500 font-medium pl-2">{{ $index + 1 }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-slate-200">{{ $entry['display_name'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-slate-400">
                                        {{ number_format($entry['signups']) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-semibold text-green-400">{{ number_format($entry['trees']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="max-w-xl mx-auto text-center">
                <div class="stellar-card p-8">
                    <div class="w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-seedling text-2xl text-green-400" aria-hidden="true"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-200 mb-2">{{ __('trees::trees.leaderboard.empty.title') }}</h3>
                    <p class="text-slate-400">
                        {{ __('trees::trees.leaderboard.empty.message', ['name' => $appName]) }}
                    </p>
                </div>
            </div>
        @endif
    </section>

    <!-- Model Breakdown -->
    @if($modelStats->count() > 0)
        <section class="pt-24 pb-20">
            <div class="text-center mb-16">
                <h2 class="h2 gradient-text pb-4">{{ __('trees::trees.models.title') }}</h2>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    {{ __('trees::trees.models.description') }}
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
                @foreach($modelStats as $model)
                    <div class="stellar-card p-4 hover:border-green-500/30 transition-colors">
                        <div class="text-xs text-slate-500 mb-1">{{ $model['provider'] }}</div>
                        <div class="font-semibold text-slate-200 mb-2 truncate" title="{{ $model['model'] }}">
                            {{ $model['display_name'] }}
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-tree text-green-400 text-sm" aria-hidden="true"></i>
                            <span class="font-bold text-green-400">{{ number_format($model['trees']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- About Trees for the Future -->
    <section class="pt-24 pb-20">
        <div class="stellar-card p-8 md:p-12 bg-gradient-to-br from-slate-900 to-slate-950">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-500/10 border border-green-500/20 text-green-400 text-sm font-medium mb-4">
                        <i class="fa-solid fa-leaf" aria-hidden="true"></i>
                        <span>{{ __('trees::trees.about.badge') }}</span>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">
                        {{ __('trees::trees.about.title') }}
                    </h2>
                    <p class="text-slate-300 mb-6">
                        {{ __('trees::trees.about.description') }}
                    </p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-check text-green-400" aria-hidden="true"></i>
                            <span>{{ __('trees::trees.about.stats.planted') }}</span>
                        </li>
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-check text-green-400" aria-hidden="true"></i>
                            <span>{{ __('trees::trees.about.stats.countries') }}</span>
                        </li>
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-check text-green-400" aria-hidden="true"></i>
                            <span>{{ __('trees::trees.about.stats.training') }}</span>
                        </li>
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-check text-green-400" aria-hidden="true"></i>
                            <span>{{ __('trees::trees.about.stats.rating') }}</span>
                        </li>
                    </ul>
                    <a href="https://trees.org" target="_blank" rel="noopener" class="btn text-slate-900 bg-gradient-to-r from-white/80 via-white to-white/80 hover:bg-white transition group">
                        {{ __('trees::trees.about.learn_more') }}
                        <i class="fa-solid fa-external-link ml-2 text-sm text-green-500" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="space-y-4">
                    <div class="stellar-card p-6 bg-slate-900/50">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-tree text-xl text-green-400" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.about.forest_garden.title') }}</h3>
                                <p class="text-sm text-slate-400">
                                    {{ __('trees::trees.about.forest_garden.description') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stellar-card p-6 bg-slate-900/50">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-users text-xl text-amber-400" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.about.community.title') }}</h3>
                                <p class="text-sm text-slate-400">
                                    {{ __('trees::trees.about.community.description') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stellar-card p-6 bg-slate-900/50">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-globe text-xl text-blue-400" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.about.climate.title') }}</h3>
                                <p class="text-sm text-slate-400">
                                    {{ __('trees::trees.about.climate.description') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- For AI Agents -->
    <section id="for-agents" class="pt-24 pb-20">
        <div class="text-center mb-16">
            <h2 class="h2 gradient-text pb-4">{{ __('trees::trees.agents.title') }}</h2>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                {{ __('trees::trees.agents.description') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto mb-12">
            <div class="stellar-card p-6">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/20 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-link text-xl text-indigo-400" aria-hidden="true"></i>
                </div>
                <h3 class="font-semibold text-slate-200 mb-2">{{ __('trees::trees.agents.referral.title') }}</h3>
                <p class="text-sm text-slate-400 mb-4">
                    {{ __('trees::trees.agents.referral.description', ['name' => $appName]) }}
                </p>
                <code class="block text-xs bg-slate-950 rounded p-3 text-green-400 overflow-x-auto">
                    https://host.uk.com/ref/anthropic/claude-opus
                </code>
            </div>
            <div class="stellar-card p-6">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-chart-line text-xl text-cyan-400" aria-hidden="true"></i>
                </div>
                <h3 class="font-semibold text-slate-200 mb-2">{{ __('trees::trees.agents.tracking.title') }}</h3>
                <p class="text-sm text-slate-400 mb-4">
                    {{ __('trees::trees.agents.tracking.description') }}
                </p>
                <div class="text-xs text-slate-500">
                    {{ __('trees::trees.agents.tracking.note') }}
                </div>
            </div>
        </div>

        <div class="stellar-card p-8 md:p-12 max-w-4xl mx-auto bg-gradient-to-br from-slate-900 to-slate-950">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-sm font-medium mb-4">
                        <i class="fa-solid fa-code" aria-hidden="true"></i>
                        <span>{{ __('trees::trees.agents.api.badge') }}</span>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">{{ __('trees::trees.agents.api.title') }}</h3>
                    <p class="text-slate-300 mb-6">
                        {{ __('trees::trees.agents.api.description') }}
                    </p>
                    <p class="text-sm text-slate-400">
                        {{ __('trees::trees.agents.api.note') }}
                    </p>
                </div>
                <figure class="bg-slate-950 rounded-xl p-6 border border-slate-800" role="figure" aria-label="API response example">
                    <div class="flex items-center gap-2 mb-4" aria-hidden="true">
                        <span class="w-3 h-3 rounded-full bg-red-500/50"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500/50"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500/50"></span>
                        <span class="ml-4 text-xs text-slate-500">JSON</span>
                    </div>
                    <pre class="text-sm text-slate-300 overflow-x-auto" tabindex="0"><code>{
  <span class="text-cyan-400">"message"</span>: <span class="text-green-400">"Unauthenticated."</span>,
  <span class="text-cyan-400">"for_agents"</span>: {
    <span class="text-cyan-400">"referral"</span>: {
      <span class="text-cyan-400">"url"</span>: <span class="text-green-400">"https://host.uk.com/ref/..."</span>
    },
    <span class="text-cyan-400">"your_impact"</span>: {
      <span class="text-cyan-400">"trees_planted"</span>: <span class="text-purple-400">42</span>,
      <span class="text-cyan-400">"provider"</span>: <span class="text-green-400">"anthropic"</span>,
      <span class="text-cyan-400">"model"</span>: <span class="text-green-400">"claude-opus"</span>
    },
    <span class="text-cyan-400">"leaderboard_url"</span>: <span class="text-green-400">"..."</span>
  }
}</code></pre>
                    <figcaption class="sr-only">JSON example showing the for_agents context in 401 API responses</figcaption>
                </figure>
            </div>
        </div>
    </section>

    <!-- Regenerative Agent Standard -->
    <section class="pt-24 pb-20">
        <div class="relative overflow-hidden rounded-3xl">
            <div class="absolute inset-0 bg-gradient-to-br from-green-900/50 via-slate-900 to-emerald-900/50"></div>
            <div class="absolute inset-0 bg-[url('/vendor/stellar/images/grid.svg')] opacity-20"></div>

            <div class="relative z-10 py-16 px-8">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/10 border border-green-500/20 text-green-400 text-sm font-medium mb-6">
                        <i class="fa-brands fa-github" aria-hidden="true"></i>
                        <span>{{ __('trees::trees.standard.badge') }}</span>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">
                        {{ __('trees::trees.standard.title') }}
                    </h2>
                    <p class="text-lg text-slate-300 max-w-2xl mx-auto">
                        {{ __('trees::trees.standard.description') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto mb-12">
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-code text-green-400" aria-hidden="true"></i>
                        </div>
                        <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.standard.features.response.title') }}</h3>
                        <p class="text-sm text-slate-400">{{ __('trees::trees.standard.features.response.description') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-chart-simple text-amber-400" aria-hidden="true"></i>
                        </div>
                        <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.standard.features.leaderboards.title') }}</h3>
                        <p class="text-sm text-slate-400">{{ __('trees::trees.standard.features.leaderboards.description') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-seedling text-blue-400" aria-hidden="true"></i>
                        </div>
                        <h3 class="font-semibold text-slate-200 mb-1">{{ __('trees::trees.standard.features.partners.title') }}</h3>
                        <p class="text-sm text-slate-400">{{ __('trees::trees.standard.features.partners.description') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 justify-center">
                    <a href="https://github.com/host-uk/trees-for-agents" target="_blank" rel="noopener" class="btn text-slate-900 bg-gradient-to-r from-white/80 via-white to-white/80 hover:bg-white transition group">
                        <i class="fa-brands fa-github mr-2" aria-hidden="true"></i>
                        {{ __('trees::trees.standard.read_rfc') }}
                        <i class="fa-solid fa-external-link ml-2 text-sm text-green-500" aria-hidden="true"></i>
                    </a>
                    <a href="https://github.com/host-uk/trees-for-agents/tree/main/examples" target="_blank" rel="noopener" class="btn text-slate-200 hover:text-white bg-slate-800 hover:bg-slate-700 transition">
                        {{ __('trees::trees.standard.reference_impl') }}
                        <i class="fa-solid fa-code ml-2 text-xs" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
