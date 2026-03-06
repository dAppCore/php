<div class="space-y-6">
    {{-- Notification Messages --}}
    @if ($saveMessage)
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $saveMessage }}</p>
                </div>
                <div class="ml-auto pl-3">
                    <button wire:click="clearMessages" type="button" class="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Header Section --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Security Headers Configuration</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure HTTP security headers for your application.</p>
        </div>
        <div class="flex items-center space-x-2">
            <label for="headers-enabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable Headers</label>
            <button
                wire:click="$toggle('headersEnabled')"
                type="button"
                class="{{ $headersEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                role="switch"
                aria-checked="{{ $headersEnabled ? 'true' : 'false' }}"
            >
                <span class="{{ $headersEnabled ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
            </button>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            @foreach (['csp' => 'Content Security Policy', 'hsts' => 'HSTS', 'permissions' => 'Permissions Policy', 'other' => 'Other Headers'] as $tab => $label)
                <button
                    wire:click="setTab('{{ $tab }}')"
                    type="button"
                    class="{{ $activeTab === $tab ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- CSP Tab --}}
    @if ($activeTab === 'csp')
        <div class="space-y-6">
            {{-- CSP Enable/Disable --}}
            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Content Security Policy</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Control which resources can be loaded.</p>
                </div>
                <button
                    wire:click="$toggle('cspEnabled')"
                    type="button"
                    class="{{ $cspEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                    role="switch"
                >
                    <span class="{{ $cspEnabled ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>

            @if ($cspEnabled)
                {{-- CSP Options --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex items-center space-x-3">
                        <input wire:model="cspReportOnly" type="checkbox" id="csp-report-only" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        <label for="csp-report-only" class="text-sm font-medium text-gray-700 dark:text-gray-300">Report-Only Mode</label>
                    </div>
                    <div class="flex items-center space-x-3">
                        <input wire:model="cspNonceEnabled" type="checkbox" id="csp-nonce-enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        <label for="csp-nonce-enabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable Nonce-based CSP</label>
                    </div>
                </div>

                <div>
                    <label for="csp-report-uri" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Report URI</label>
                    <input wire:model="cspReportUri" type="url" id="csp-report-uri" placeholder="https://example.com/csp-report" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                </div>

                {{-- CSP Directives --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">CSP Directives</h4>
                        @if (count($this->getAvailableDirectives()) > 0)
                            <div class="relative">
                                <select wire:change="addDirective($event.target.value); $event.target.value=''" class="block rounded-md border-gray-300 py-1.5 pl-3 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="">Add directive...</option>
                                    @foreach ($this->getAvailableDirectives() as $directive)
                                        <option value="{{ $directive }}">{{ $directive }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @foreach ($cspDirectives as $directive => $value)
                            <div class="flex items-start space-x-3">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $directive }}</label>
                                    <input
                                        wire:model.blur="cspDirectives.{{ $directive }}"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                                        placeholder="'self' https://example.com"
                                    >
                                </div>
                                <button wire:click="removeDirective('{{ $directive }}')" type="button" class="mt-6 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-700">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- External Services --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">External Services</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex items-center space-x-3">
                            <input wire:model="jsdelivrEnabled" type="checkbox" id="jsdelivr-enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                            <label for="jsdelivr-enabled" class="text-sm text-gray-700 dark:text-gray-300">jsDelivr CDN</label>
                        </div>
                        <div class="flex items-center space-x-3">
                            <input wire:model="unpkgEnabled" type="checkbox" id="unpkg-enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                            <label for="unpkg-enabled" class="text-sm text-gray-700 dark:text-gray-300">unpkg CDN</label>
                        </div>
                        <div class="flex items-center space-x-3">
                            <input wire:model="googleAnalyticsEnabled" type="checkbox" id="ga-enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                            <label for="ga-enabled" class="text-sm text-gray-700 dark:text-gray-300">Google Analytics</label>
                        </div>
                        <div class="flex items-center space-x-3">
                            <input wire:model="facebookEnabled" type="checkbox" id="fb-enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                            <label for="fb-enabled" class="text-sm text-gray-700 dark:text-gray-300">Facebook SDK</label>
                        </div>
                    </div>
                </div>

                {{-- CSP Preview --}}
                <div class="rounded-lg bg-gray-900 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-400">CSP Header Preview</span>
                    </div>
                    <pre class="mt-2 overflow-x-auto text-xs text-green-400"><code>{{ $this->previewCspHeader() }}</code></pre>
                </div>
            @endif
        </div>
    @endif

    {{-- HSTS Tab --}}
    @if ($activeTab === 'hsts')
        <div class="space-y-6">
            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">HTTP Strict Transport Security</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Enforce HTTPS connections.</p>
                </div>
                <button
                    wire:click="$toggle('hstsEnabled')"
                    type="button"
                    class="{{ $hstsEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                    role="switch"
                >
                    <span class="{{ $hstsEnabled ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>

            @if ($hstsEnabled)
                <div>
                    <label for="hsts-max-age" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Age (seconds)</label>
                    <input wire:model="hstsMaxAge" type="number" id="hsts-max-age" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Recommended: 31536000 (1 year)</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <input wire:model="hstsIncludeSubdomains" type="checkbox" id="hsts-subdomains" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        <label for="hsts-subdomains" class="text-sm font-medium text-gray-700 dark:text-gray-300">Include Subdomains</label>
                    </div>
                    <div class="flex items-center space-x-3">
                        <input wire:model="hstsPreload" type="checkbox" id="hsts-preload" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        <label for="hsts-preload" class="text-sm font-medium text-gray-700 dark:text-gray-300">Preload</label>
                    </div>
                </div>

                <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        <strong>Note:</strong> HSTS headers are only sent in production environments to prevent development issues.
                    </p>
                </div>
            @endif
        </div>
    @endif

    {{-- Permissions Policy Tab --}}
    @if ($activeTab === 'permissions')
        <div class="space-y-6">
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Permissions Policy</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Control browser features and APIs.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissionsFeatures as $feature => $config)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $feature }}</label>
                            <button
                                wire:click="togglePermission('{{ $feature }}')"
                                type="button"
                                class="{{ $config['enabled'] ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }} relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                role="switch"
                            >
                                <span class="{{ $config['enabled'] ? 'translate-x-4' : 'translate-x-0' }} pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                            </button>
                        </div>
                        @if ($config['enabled'])
                            <input
                                wire:model.blur="permissionsFeatures.{{ $feature }}.allowlist"
                                type="text"
                                class="mt-2 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="self https://example.com"
                            >
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Other Headers Tab --}}
    @if ($activeTab === 'other')
        <div class="space-y-6">
            <div>
                <label for="x-frame-options" class="block text-sm font-medium text-gray-700 dark:text-gray-300">X-Frame-Options</label>
                <select wire:model="xFrameOptions" id="x-frame-options" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                    <option value="DENY">DENY</option>
                    <option value="SAMEORIGIN">SAMEORIGIN</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Prevents clickjacking attacks by controlling iframe embedding.</p>
            </div>

            <div>
                <label for="referrer-policy" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Referrer-Policy</label>
                <select wire:model="referrerPolicy" id="referrer-policy" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                    <option value="no-referrer">no-referrer</option>
                    <option value="no-referrer-when-downgrade">no-referrer-when-downgrade</option>
                    <option value="origin">origin</option>
                    <option value="origin-when-cross-origin">origin-when-cross-origin</option>
                    <option value="same-origin">same-origin</option>
                    <option value="strict-origin">strict-origin</option>
                    <option value="strict-origin-when-cross-origin">strict-origin-when-cross-origin</option>
                    <option value="unsafe-url">unsafe-url</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Controls how much referrer information is sent with requests.</p>
            </div>

            <div class="space-y-2 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Fixed Headers</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">These headers are always included for security:</p>
                <ul class="list-inside list-disc text-xs text-gray-600 dark:text-gray-300">
                    <li>X-Content-Type-Options: nosniff</li>
                    <li>X-XSS-Protection: 1; mode=block</li>
                </ul>
            </div>
        </div>
    @endif

    {{-- Action Buttons --}}
    <div class="flex items-center justify-between border-t border-gray-200 pt-6 dark:border-gray-700">
        <button wire:click="resetToDefaults" type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:ring-gray-600 dark:hover:bg-gray-700">
            Reset to Defaults
        </button>
        <div class="flex space-x-3">
            <button
                wire:click="$dispatch('show-env-config')"
                type="button"
                class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:ring-gray-600 dark:hover:bg-gray-700"
                x-data
                x-on:click="$dispatch('open-modal', 'env-config')"
            >
                Export .env
            </button>
            <button wire:click="saveConfiguration" type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Save Configuration
            </button>
        </div>
    </div>
</div>
