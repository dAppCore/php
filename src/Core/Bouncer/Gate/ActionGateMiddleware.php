<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Action Gate Middleware - enforces action whitelisting.
 *
 * Intercepts requests and checks if the target action is permitted.
 *
 * ## Integration
 *
 * ```
 * Request -> BouncerGate (action whitelisting) -> Laravel Gate/Policy -> Controller
 * ```
 *
 * ## Behavior by Mode
 *
 * **Production (training_mode = false):**
 * - Allowed actions proceed normally
 * - Unknown/denied actions return 403 Forbidden
 *
 * **Training Mode (training_mode = true):**
 * - Allowed actions proceed normally
 * - Unknown actions return a training response:
 *   - API requests: JSON with action details and approval prompt
 *   - Web requests: Redirect back with flash message
 */
class ActionGateMiddleware
{
    public function __construct(
        protected ActionGateService $gateService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip for routes that explicitly bypass the gate
        if ($request->route()?->getAction('bypass_gate')) {
            return $next($request);
        }

        $result = $this->gateService->check($request);

        return match ($result['result']) {
            ActionGateService::RESULT_ALLOWED => $next($request),
            ActionGateService::RESULT_TRAINING => $this->trainingResponse($request, $result),
            default => $this->deniedResponse($request, $result),
        };
    }

    /**
     * Generate response for training mode.
     */
    protected function trainingResponse(Request $request, array $result): Response
    {
        $action = $result['action'];
        $scope = $result['scope'];

        if ($this->wantsJson($request)) {
            return $this->trainingJsonResponse($request, $action, $scope);
        }

        return $this->trainingWebResponse($request, $action, $scope);
    }

    /**
     * JSON response for training mode (API requests).
     */
    protected function trainingJsonResponse(Request $request, string $action, ?string $scope): JsonResponse
    {
        return response()->json([
            'error' => 'action_not_trained',
            'message' => "Action '{$action}' is not trained. Approve this action to continue.",
            'action' => $action,
            'scope' => $scope,
            'route' => $request->path(),
            'method' => $request->method(),
            'training_mode' => true,
            'approval_url' => $this->approvalUrl($action, $scope, $request),
        ], 403);
    }

    /**
     * Web response for training mode (browser requests).
     */
    protected function trainingWebResponse(Request $request, string $action, ?string $scope): RedirectResponse
    {
        $message = "Action '{$action}' requires training approval.";

        return redirect()
            ->back()
            ->with('bouncer_training', [
                'action' => $action,
                'scope' => $scope,
                'route' => $request->path(),
                'method' => $request->method(),
                'message' => $message,
            ])
            ->withInput();
    }

    /**
     * Generate response for denied action.
     */
    protected function deniedResponse(Request $request, array $result): Response
    {
        $action = $result['action'];

        if ($this->wantsJson($request)) {
            return response()->json([
                'error' => 'action_denied',
                'message' => "Action '{$action}' is not permitted.",
                'action' => $action,
            ], 403);
        }

        abort(403, "Action '{$action}' is not permitted.");
    }

    /**
     * Check if request expects JSON response.
     */
    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*')
            || $request->header('Accept') === 'application/json';
    }

    /**
     * Generate URL for approving an action.
     */
    protected function approvalUrl(string $action, ?string $scope, Request $request): string
    {
        return route('bouncer.gate.approve', [
            'action' => $action,
            'scope' => $scope,
            'redirect' => $request->fullUrl(),
        ]);
    }
}
