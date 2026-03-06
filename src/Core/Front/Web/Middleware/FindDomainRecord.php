<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Web\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve workspace from incoming domain.
 *
 * Sets workspace_model and workspace attributes on the request.
 * Speed is king - this middleware does ONE thing: find domain record.
 */
class FindDomainRecord
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Core domains serve the main marketing site - pass through
        if ($this->isCoreDomain($host)) {
            return $next($request);
        }

        // Try to find a workspace for this domain
        $workspace = $this->resolveWorkspaceFromDomain($host);

        if ($workspace) {
            $request->attributes->set('workspace_model', $workspace);
            $request->attributes->set('workspace', $workspace->slug);
        }

        // Always pass through - routing handles the rest
        return $next($request);
    }

    /**
     * Check if the host is a core domain (serves main marketing site).
     */
    protected function isCoreDomain(string $host): bool
    {
        // Always allow localhost/IP
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        // Get base domain from config
        $baseDomain = config('core.domain.base', 'core.test');

        // Check if it's the base domain or www subdomain
        if ($host === $baseDomain || $host === 'www.'.$baseDomain) {
            return true;
        }

        // Check against configured excluded domains
        $excludedDomains = config('core.domain.excluded', []);
        if (in_array($host, $excludedDomains, true)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve workspace from the domain.
     *
     * Requires Core\Tenant module to be installed.
     *
     * @return object|null Workspace model instance or null
     */
    protected function resolveWorkspaceFromDomain(string $host): ?object
    {
        // Check if Tenant module is installed
        if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
            return null;
        }

        // Check for custom domain first
        $workspace = \Core\Tenant\Models\Workspace::where('domain', $host)->first();
        if ($workspace) {
            return $workspace;
        }

        // Check for subdomain of base domain
        $baseDomain = config('core.domain.base', 'core.test');

        if (str_ends_with($host, '.'.$baseDomain)) {
            $subdomain = str_replace('.'.$baseDomain, '', $host);
            $parts = explode('.', $subdomain);

            if (count($parts) >= 1) {
                $workspaceSlug = $parts[0];

                return \Core\Tenant\Models\Workspace::where('slug', $workspaceSlug)
                    ->where('is_active', true)
                    ->first();
            }
        }

        return null;
    }
}
