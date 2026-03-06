<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Early-exit middleware for blocking bad actors and handling redirects.
 *
 * Runs FIRST before any other middleware to:
 * 1. Set trusted proxies (so we get real client IP)
 * 2. Block bad actors (honeypot critical hits)
 * 3. Handle SEO redirects (301/302)
 *
 * This replaces Laravel's TrustProxies middleware - all early-exit logic in one place.
 */
class BouncerMiddleware
{
    public function __construct(
        protected BlocklistService $blocklist,
        protected RedirectService $redirects,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Trust proxies first - need real client IP for everything else
        $this->setTrustedProxies($request);

        $ip = $request->ip();
        $path = $request->path();

        // Check blocklist - fastest rejection
        if ($this->blocklist->isBlocked($ip)) {
            return $this->blockedResponse($ip);
        }

        // Check SEO redirects
        if ($redirect = $this->redirects->match($path)) {
            return redirect($redirect['to'], $redirect['status']);
        }

        return $next($request);
    }

    /**
     * Configure trusted proxies for correct client IP detection.
     *
     * TRUSTED_PROXIES env var: comma-separated IPs or '*' for all.
     * Production: set to load balancer IPs (e.g., hermes.lb.host.uk.com)
     * Development: defaults to '*' (trust all)
     */
    protected function setTrustedProxies(Request $request): void
    {
        $trustedProxies = env('TRUSTED_PROXIES', '*');

        $proxies = $trustedProxies === '*'
            ? $request->server->get('REMOTE_ADDR') // Trust the immediate proxy
            : explode(',', $trustedProxies);

        $request->setTrustedProxies(
            is_array($proxies) ? $proxies : [$proxies],
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
    }

    /**
     * Response for blocked IPs - minimal processing.
     */
    protected function blockedResponse(string $ip): Response
    {
        return response('🫖', 418, [
            'Content-Type' => 'text/plain',
            'X-Blocked' => 'true',
            'X-Powered-By' => 'Earl Grey',
        ]);
    }
}
