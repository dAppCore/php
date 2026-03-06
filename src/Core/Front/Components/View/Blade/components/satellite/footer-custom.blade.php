{{--
    Custom Footer Content Partial

    Variables:
        $customContent   - Raw HTML content
        $customLinks     - Array of ['label' => '', 'url' => '', 'icon' => ''] links
        $socialLinks     - Array of ['platform' => '', 'url' => '', 'icon' => ''] social links
        $contactEmail    - Email address
        $contactPhone    - Phone number
        $showCopyright   - Whether to show copyright (for replace mode)
        $copyrightText   - Custom copyright text
        $workspaceName   - Workspace name for default copyright
        $appName         - App name for default copyright
        $appIcon         - App icon path
--}}
@php
    $showCopyright = $showCopyright ?? false;
    $copyrightText = $copyrightText ?? null;
    $workspaceName = $workspaceName ?? null;
    $appName = $appName ?? config('core.app.name', 'Core PHP');
    $appIcon = $appIcon ?? config('core.app.icon', '/images/icon.svg');
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
    {{-- Raw HTML custom content --}}
    @if(!empty($customContent))
        <div class="custom-footer-content mb-6">
            {!! $customContent !!}
        </div>
    @endif

    {{-- Structured content grid --}}
    @if(!empty($customLinks) || !empty($socialLinks) || $contactEmail || $contactPhone)
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 @if(!empty($customContent)) pt-6 border-t border-slate-200 dark:border-slate-700/50 @endif">

            {{-- Contact information --}}
            @if($contactEmail || $contactPhone)
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 text-sm text-slate-500">
                    @if($contactEmail)
                        <a href="mailto:{{ $contactEmail }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition flex items-center gap-2">
                            <i class="fa-solid fa-envelope text-xs"></i>
                            {{ $contactEmail }}
                        </a>
                    @endif
                    @if($contactPhone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition flex items-center gap-2">
                            <i class="fa-solid fa-phone text-xs"></i>
                            {{ $contactPhone }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Custom links --}}
            @if(!empty($customLinks))
                <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500">
                    @foreach($customLinks as $link)
                        <a href="{{ $link['url'] }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition flex items-center gap-2">
                            @if(!empty($link['icon']))
                                <i class="{{ $link['icon'] }} text-xs"></i>
                            @endif
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Social links --}}
            @if(!empty($socialLinks))
                <div class="flex items-center gap-4">
                    @foreach($socialLinks as $social)
                        @php
                            // Get icon from the social link or generate based on platform
                            $socialIcon = $social['icon'] ?? match(strtolower($social['platform'] ?? '')) {
                                'twitter', 'x' => 'fa-brands fa-x-twitter',
                                'facebook' => 'fa-brands fa-facebook',
                                'instagram' => 'fa-brands fa-instagram',
                                'linkedin' => 'fa-brands fa-linkedin',
                                'youtube' => 'fa-brands fa-youtube',
                                'tiktok' => 'fa-brands fa-tiktok',
                                'github' => 'fa-brands fa-github',
                                'discord' => 'fa-brands fa-discord',
                                'mastodon' => 'fa-brands fa-mastodon',
                                'bluesky' => 'fa-brands fa-bluesky',
                                'threads' => 'fa-brands fa-threads',
                                'pinterest' => 'fa-brands fa-pinterest',
                                default => 'fa-solid fa-link',
                            };
                        @endphp
                        <a
                            href="{{ $social['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                            aria-label="{{ ucfirst($social['platform'] ?? 'Social link') }}"
                        >
                            <i class="{{ $socialIcon }}"></i>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Copyright for replace mode --}}
    @if($showCopyright)
        <div class="flex items-center gap-4 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700/50">
            <img src="{{ $appIcon }}" alt="{{ $appName }}" class="w-6 h-6 opacity-50">
            <span class="text-sm text-slate-500">
                {!! $copyrightText ?? '&copy; '.date('Y').' '.e($workspaceName ?? $appName) !!}
            </span>
        </div>
    @endif
</div>
