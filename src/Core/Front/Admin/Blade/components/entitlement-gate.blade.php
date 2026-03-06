@props([
    'entitled' => false,
    'authenticated' => false,
    'icon' => 'lock',
    'title' => null,
    'description' => null,
    'featureName' => 'this feature',
    'ctaUrl' => null,
    'ctaText' => null,
    'ctaIcon' => 'rocket',
    'variant' => 'upgrade', // upgrade, waitlist, boost, custom
    'showSignIn' => true,
    'blurAmount' => 'sm', // sm, md, lg, xl
])

@php
    // Default titles based on variant
    $defaultTitles = [
        'upgrade' => $authenticated ? 'Upgrade to unlock' : 'Sign in to continue',
        'waitlist' => 'Join the waitlist',
        'boost' => 'Add a boost',
        'custom' => $title ?? 'Unlock this feature',
    ];

    // Default descriptions based on variant
    $defaultDescriptions = [
        'upgrade' => $authenticated
            ? "{$featureName} requires a paid plan. Upgrade to unlock this feature and more."
            : "Sign in to access {$featureName} and all your workspace features.",
        'waitlist' => "Get early access to {$featureName}. Join thousands of creators already on the waitlist.",
        'boost' => "Add a {$featureName} boost to your workspace to unlock this feature.",
        'custom' => $description ?? "You need access to use {$featureName}.",
    ];

    // Default CTAs based on variant
    $defaultCtas = [
        'upgrade' => ['url' => '/hub/settings', 'text' => 'View plans'],
        'waitlist' => ['url' => '/waitlist', 'text' => 'Join the waitlist'],
        'boost' => ['url' => '/hub/settings', 'text' => 'Add boost'],
        'custom' => ['url' => $ctaUrl ?? '#', 'text' => $ctaText ?? 'Get access'],
    ];

    // Default icons based on variant
    $defaultIcons = [
        'upgrade' => 'crown',
        'waitlist' => 'clock',
        'boost' => 'bolt',
        'custom' => $icon,
    ];

    $resolvedTitle = $title ?? $defaultTitles[$variant] ?? $defaultTitles['custom'];
    $resolvedDescription = $description ?? $defaultDescriptions[$variant] ?? $defaultDescriptions['custom'];
    $resolvedCtaUrl = $ctaUrl ?? $defaultCtas[$variant]['url'] ?? '#';
    $resolvedCtaText = $ctaText ?? $defaultCtas[$variant]['text'] ?? 'Get access';
    $resolvedIcon = $icon !== 'lock' ? $icon : ($defaultIcons[$variant] ?? 'lock');

    $blurClasses = [
        'sm' => 'blur-sm',
        'md' => 'blur-md',
        'lg' => 'blur-lg',
        'xl' => 'blur-xl',
    ];
    $blurClass = $blurClasses[$blurAmount] ?? 'blur-sm';
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }}>
    {{-- Content (blurred when not entitled) --}}
    <div @class([
        'transition-all duration-300',
        "{$blurClass} pointer-events-none select-none" => !$entitled,
    ])>
        {{ $slot }}
    </div>

    {{-- Overlay when not entitled --}}
    @unless($entitled)
        <div class="absolute inset-0 flex items-center justify-center z-10">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-8 max-w-md mx-4 text-center shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-violet-100 dark:bg-violet-500/20 flex items-center justify-center">
                    <core:icon :name="$resolvedIcon" class="text-2xl text-violet-600 dark:text-violet-400" />
                </div>

                <core:heading level="3" class="mb-3 text-gray-900 dark:text-white">
                    {{ $resolvedTitle }}
                </core:heading>

                <core:text class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ $resolvedDescription }}
                </core:text>

                <div class="space-y-3">
                    @if($authenticated || $variant === 'waitlist')
                        <flux:button href="{{ $resolvedCtaUrl }}" variant="primary" class="w-full">
                            <core:icon :name="$ctaIcon" class="mr-2" />
                            {{ $resolvedCtaText }}
                        </flux:button>
                    @else
                        <flux:button href="/login" variant="primary" class="w-full">
                            <core:icon name="right-to-bracket" class="mr-2" />
                            Sign in
                        </flux:button>
                    @endif

                    @if($showSignIn && !$authenticated && $variant !== 'waitlist')
                        <flux:button href="/register" variant="ghost" class="w-full">
                            Don't have an account? Sign up
                        </flux:button>
                    @endif
                </div>

                @if($variant === 'waitlist')
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        <core:icon name="shield-halved" class="mr-1" />
                        No spam, ever. Unsubscribe anytime.
                    </p>
                @endif
            </div>
        </div>
    @endunless
</div>
