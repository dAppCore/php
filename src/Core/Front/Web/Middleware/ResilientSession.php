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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resilient Session Middleware
 *
 * Catches session-related exceptions (decryption errors, database errors)
 * and handles them gracefully by clearing corrupted cookies and allowing
 * the request to continue with a fresh session.
 *
 * This prevents 503 errors from session corruption or APP_KEY changes.
 */
class ResilientSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Try to start/access the session early to catch any issues
            if ($request->hasSession()) {
                // Force session start to catch any decryption/database errors
                $request->session()->start();
            }

            return $next($request);
        } catch (DecryptException $e) {
            return $this->handleSessionError($request, $e, 'decrypt');
        } catch (\Illuminate\Database\QueryException $e) {
            // Only catch session-related query exceptions
            if ($this->isSessionError($e)) {
                return $this->handleSessionError($request, $e, 'database');
            }
            throw $e;
        } catch (\PDOException $e) {
            // Catch PDO exceptions that might be session-related
            if ($this->isSessionError($e)) {
                return $this->handleSessionError($request, $e, 'pdo');
            }
            throw $e;
        }
    }

    /**
     * Handle a session error by clearing cookies and redirecting.
     */
    protected function handleSessionError(Request $request, \Throwable $e, string $type): Response
    {
        Log::warning("[ResilientSession] Session {$type} error - clearing cookies", [
            'message' => $e->getMessage(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->ip(),
        ]);

        $sessionCookie = config('session.cookie', 'laravel_session');

        // For AJAX/API requests, return JSON error
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Session expired. Please retry your request.',
                'error' => 'session_expired',
            ], 419)
                ->withCookie(cookie()->forget($sessionCookie))
                ->withCookie(cookie()->forget('XSRF-TOKEN'));
        }

        // For web requests, redirect to same page with cleared cookies
        return redirect($request->getRequestUri())
            ->withCookie(cookie()->forget($sessionCookie))
            ->withCookie(cookie()->forget('XSRF-TOKEN'));
    }

    /**
     * Check if an exception is likely session-related.
     */
    protected function isSessionError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        $sessionTable = config('session.table', 'sessions');

        return str_contains($message, $sessionTable)
            || str_contains($message, 'session')
            || str_contains($message, 'payload');
    }
}
