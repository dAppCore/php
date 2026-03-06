@props([
    'minimal' => false,
    'transparent' => false,
])

<header class="fixed w-full z-30 {{ $transparent ? 'bg-transparent' : 'bg-slate-900/80 backdrop-blur-sm border-b border-slate-800' }}">
    <div class="{{ $minimal ? 'max-w-3xl' : 'max-w-6xl' }} mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16 md:h-20">

            <!-- Site branding -->
            <div class="{{ $minimal ? '' : 'flex-1' }}">
                <a class="inline-flex items-center gap-2" href="/" aria-label="Host">
                    <img src="/images/vi/vi_icon.webp" alt="Host" class="w-10 h-10 drop-shadow-[0_2px_8px_rgba(139,92,246,0.4)]">
                    <span class="font-bold text-xl text-white">Host</span>
                </a>
            </div>

            @unless($minimal)
                <!-- Desktop navigation -->
                <flux:navbar class="hidden md:flex md:grow justify-center">
                    <!-- Services dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="purple">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('services*') || request()->routeIs('pricing')">
                            <x-fa-icon icon="sparkles" class="mr-1.5 text-purple-400" />Services
                        </flux:navbar.item>

                        <flux:navmenu class="w-56">
                            <flux:navmenu.item href="/services" icon="squares-2x2">
                                All Services
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/pricing" icon="currency-pound">
                                Pricing
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:navmenu.item href="/services/bio" icon="link">
                                <x-service-name service="bio" />
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/services/social" icon="share">
                                <x-service-name service="social" />
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/services/analytics" icon="chart-bar">
                                <x-service-name service="analytics" />
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/services/trust" icon="shield-check">
                                <x-service-name service="trust" />
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/services/notify" icon="bell">
                                <x-service-name service="notify" />
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/services/mail" icon="envelope">
                                <x-service-name service="mail" />
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:navmenu.item href="/services/seo" icon="magnifying-glass">
                                SEO Agency
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/partner" icon="building-office-2">
                                Partners
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>

                    <!-- For dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="indigo">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('for') || request()->routeIs('for.*')">
                            <x-fa-icon icon="users" class="mr-1.5 text-indigo-400" />For
                        </flux:navbar.item>

                        <flux:navmenu class="w-52">
                            <flux:navmenu.item href="/for" icon="squares-2x2">
                                All Audiences
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:navmenu.item href="/for/content-creators" icon="sparkles">
                                Content Creators
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/for/fansites" icon="heart">
                                FanSites
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/for/of-agencies" icon="building-office-2">
                                OF Agencies
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/for/social-media" icon="share">
                                Social Media
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/for/streamers" icon="video-camera">
                                Streamers
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>

                    <!-- AI dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="orange">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('ai') || request()->routeIs('ai.*') || request()->routeIs('trees')">
                            <x-fa-icon icon="wand-magic-sparkles" class="mr-1.5 text-orange-400" />AI
                        </flux:navbar.item>

                        <flux:navmenu class="w-52">
                            <flux:navmenu.item href="/ai" icon="cpu-chip">
                                AI Platform
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/ai/mcp" icon="server-stack">
                                MCP Servers
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/ai/ethics" icon="scale">
                                Ethics Framework
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:navmenu.item href="/trees" icon="globe-europe-africa">
                                Trees for Agents
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>

                    <!-- Tools dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="cyan">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('tools*')">
                            <x-fa-icon icon="wrench" class="mr-1.5 text-cyan-400" />Tools
                        </flux:navbar.item>

                        <flux:navmenu class="w-56">
                            <flux:navmenu.item href="/tools" icon="squares-2x2">
                                All Free Tools
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:heading size="sm" class="px-2 py-1">Popular</flux:heading>
                            <flux:navmenu.item href="/tools/lorem-ipsum" icon="document-text">
                                Lorem Ipsum Generator
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/tools/password-generator" icon="key">
                                Password Generator
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/tools/json-formatter" icon="code-bracket">
                                JSON Formatter
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/tools/qr-code-generator" icon="qr-code">
                                QR Code Generator
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>

                    <!-- OSS & Projects dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="slate">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('oss') || request()->routeIs('oss.*') || request()->routeIs('dapp-fm')">
                            <i class="fa-brands fa-github mr-1.5 text-slate-400" aria-hidden="true"></i>OSS
                        </flux:navbar.item>

                        <flux:navmenu class="w-56">
                            <flux:navmenu.item href="/oss" icon="code-bracket">
                                Open Source Projects
                            </flux:navmenu.item>
                            <flux:separator />
                            <flux:heading size="sm" class="px-2 py-1">Built on Our Stack</flux:heading>
                            <flux:navmenu.item href="/dapp-fm" icon="musical-note">
                                <div class="flex items-center justify-between w-full">
                                    <span>dapp.fm</span>
                                    <span class="text-xs text-emerald-400">Coming Soon</span>
                                </div>
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>

                    <!-- About dropdown -->
                    <flux:dropdown hover position="bottom" align="center" class="nav-dropdown" data-accent="amber">
                        <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs('about') || request()->routeIs('faq') || request()->routeIs('help.*')">
                            <x-fa-icon icon="circle-info" class="mr-1.5 text-amber-400" />About
                        </flux:navbar.item>

                        <flux:navmenu class="w-48">
                            <flux:navmenu.item href="/about" icon="building-office">
                                About Us
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/faq" icon="question-mark-circle">
                                FAQ
                            </flux:navmenu.item>
                            <flux:navmenu.item href="/help" icon="lifebuoy">
                                Help Centre
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>
                </flux:navbar>

                <!-- Desktop actions -->
                <flux:navbar class="flex-1 justify-end">
                    @auth
                        <flux:dropdown hover position="bottom" align="end" class="nav-dropdown" data-accent="violet">
                            <flux:navbar.item icon:trailing="chevron-down">
                                <x-fa-icon icon="grid-2" class="mr-1" /> Dashboard
                            </flux:navbar.item>

                            <flux:navmenu class="w-56">
                                <flux:navmenu.item href="/hub" icon="home">
                                    Hub Home
                                </flux:navmenu.item>

                                <flux:separator />

                                <flux:heading size="sm" class="px-2 py-1">Services</flux:heading>

                                <flux:navmenu.item href="/hub/sites" icon="link">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="bio" />
                                        <span class="text-xs text-violet-400">Bio Pages</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:navmenu.item href="https://social.host.uk.com" icon="share">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="social" />
                                        <span class="text-xs text-blue-400">Scheduling</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:navmenu.item href="/services/analytics" icon="chart-bar">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="analytics" />
                                        <span class="text-xs text-slate-500">Coming Soon</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:navmenu.item href="/services/trust" icon="shield-check">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="trust" />
                                        <span class="text-xs text-slate-500">Coming Soon</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:navmenu.item href="/services/notify" icon="bell">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="notify" />
                                        <span class="text-xs text-slate-500">Coming Soon</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:navmenu.item href="/services/mail" icon="envelope">
                                    <div class="flex items-center justify-between w-full">
                                        <x-service-name service="mail" />
                                        <span class="text-xs text-slate-500">Coming Soon</span>
                                    </div>
                                </flux:navmenu.item>

                                <flux:separator />

                                <flux:navmenu.item href="/hub/profile" icon="user-circle">
                                    Profile Settings
                                </flux:navmenu.item>
                            </flux:navmenu>
                        </flux:dropdown>

                        <flux:navbar.item href="/logout" class="ml-2">
                            Logout
                        </flux:navbar.item>
                    @else
                        <flux:navbar.item href="/login">
                            Login
                        </flux:navbar.item>

                        {{-- VI_DONE: waitlist CTA, queue-based early access, 50% launch bonus --}}
                        <a href="/waitlist" class="ml-2 btn-sm text-slate-900 bg-gradient-to-r from-white/80 via-white to-white/80 hover:bg-white transition duration-150 ease-in-out">
                            Get early access
                        </a>
                    @endauth
                </flux:navbar>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center ml-4" x-data="{ expanded: false }">
                    <button class="group inline-flex w-8 h-8 text-slate-300 hover:text-white text-center items-center justify-center transition"
                            aria-controls="mobile-nav"
                            :aria-expanded="expanded"
                            @click.stop="expanded = !expanded">
                        <span class="sr-only">Menu</span>
                        <svg class="w-4 h-4 fill-current pointer-events-none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                            <rect class="origin-center transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.1)] -translate-y-[5px] group-aria-expanded:rotate-[315deg] group-aria-expanded:translate-y-0" y="7" width="16" height="2" rx="1"></rect>
                            <rect class="origin-center group-aria-expanded:rotate-45 transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.8)]" y="7" width="16" height="2" rx="1"></rect>
                            <rect class="origin-center transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.1)] translate-y-[5px] group-aria-expanded:rotate-[135deg] group-aria-expanded:translate-y-0" y="7" width="16" height="2" rx="1"></rect>
                        </svg>
                    </button>

                    <!-- Mobile navigation -->
                    <nav id="mobile-nav"
                         class="absolute top-full z-20 left-0 w-full px-4 sm:px-6 overflow-hidden transition-all duration-300 ease-in-out"
                         x-ref="mobileNav"
                         :style="expanded ? 'max-height: ' + $refs.mobileNav.scrollHeight + 'px; opacity: 1' : 'max-height: 0; opacity: .8'"
                         @click.outside="expanded = false"
                         @keydown.escape.window="expanded = false"
                         x-cloak>
                        <flux:navlist class="stellar-card px-2 py-2">
                            <!-- Services -->
                            <flux:navlist.group heading="Services">
                                <flux:navlist.item href="/services" icon="squares-2x2">All Services</flux:navlist.item>
                                <flux:navlist.item href="/pricing" icon="currency-pound">Pricing</flux:navlist.item>
                                <flux:navlist.item href="/services/bio" icon="link"><x-service-name service="bio" /></flux:navlist.item>
                                <flux:navlist.item href="/services/social" icon="share"><x-service-name service="social" /></flux:navlist.item>
                                <flux:navlist.item href="/services/analytics" icon="chart-bar"><x-service-name service="analytics" /></flux:navlist.item>
                                <flux:navlist.item href="/services/trust" icon="shield-check"><x-service-name service="trust" /></flux:navlist.item>
                                <flux:navlist.item href="/services/notify" icon="bell"><x-service-name service="notify" /></flux:navlist.item>
                                <flux:navlist.item href="/services/mail" icon="envelope"><x-service-name service="mail" /></flux:navlist.item>
                                <flux:navlist.item href="/services/seo" icon="magnifying-glass">SEO Agency</flux:navlist.item>
                                <flux:navlist.item href="/partner" icon="building-office-2">Partners</flux:navlist.item>
                            </flux:navlist.group>

                            <!-- For -->
                            <flux:navlist.group heading="For" class="mt-2">
                                <flux:navlist.item href="/for" icon="squares-2x2">All Audiences</flux:navlist.item>
                                <flux:navlist.item href="/for/content-creators" icon="sparkles">Content Creators</flux:navlist.item>
                                <flux:navlist.item href="/for/fansites" icon="heart">FanSites</flux:navlist.item>
                                <flux:navlist.item href="/for/of-agencies" icon="building-office-2">OF Agencies</flux:navlist.item>
                                <flux:navlist.item href="/for/social-media" icon="share">Social Media</flux:navlist.item>
                                <flux:navlist.item href="/for/streamers" icon="video-camera">Streamers</flux:navlist.item>
                            </flux:navlist.group>

                            <!-- AI -->
                            <flux:navlist.group heading="AI" class="mt-2">
                                <flux:navlist.item href="/ai" icon="cpu-chip">AI Platform</flux:navlist.item>
                                <flux:navlist.item href="/ai/mcp" icon="server-stack">MCP Servers</flux:navlist.item>
                                <flux:navlist.item href="/ai/ethics" icon="scale">Ethics Framework</flux:navlist.item>
                                <flux:navlist.item href="/trees" icon="globe-europe-africa">Trees for Agents</flux:navlist.item>
                            </flux:navlist.group>

                            <!-- Tools -->
                            <flux:navlist.group heading="Tools" class="mt-2">
                                <flux:navlist.item href="/tools" icon="wrench-screwdriver">Free Tools</flux:navlist.item>
                            </flux:navlist.group>

                            <!-- OSS -->
                            <flux:navlist.group heading="Open Source" class="mt-2">
                                <flux:navlist.item href="/oss" icon="code-bracket">OSS Projects</flux:navlist.item>
                                <flux:navlist.item href="/dapp-fm" icon="musical-note" badge="Soon" badge:color="emerald">dapp.fm</flux:navlist.item>
                            </flux:navlist.group>

                            <!-- More -->
                            <flux:navlist.group heading="More" class="mt-2">
                                <flux:navlist.item href="/about" icon="building-office">About</flux:navlist.item>
                                <flux:navlist.item href="/faq" icon="question-mark-circle">FAQ</flux:navlist.item>
                                <flux:navlist.item href="/help" icon="lifebuoy">Help Centre</flux:navlist.item>
                            </flux:navlist.group>

                            <flux:separator class="my-2" />

                            <!-- Auth -->
                            @auth
                                <flux:navlist.item href="/hub" icon="home">Dashboard</flux:navlist.item>
                                <flux:navlist.item href="/logout" icon="arrow-right-start-on-rectangle">Logout</flux:navlist.item>
                            @else
                                <flux:navlist.item href="/login" icon="arrow-right-end-on-rectangle">Login</flux:navlist.item>
                                <flux:navlist.item href="/waitlist" icon="clipboard-document-list">Get early access</flux:navlist.item>
                            @endauth
                        </flux:navlist>
                    </nav>
                </div>
            @else
                <!-- Minimal header - just back link or support -->
                <div class="flex items-center gap-4">
                    <a href="/contact" class="text-sm text-slate-400 hover:text-white transition">
                        <x-fa-icon icon="circle-question" class="mr-1" /> Need help?
                    </a>
                </div>
            @endunless

        </div>
    </div>
</header>
