<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate;

use Core\Bouncer\Gate\Attributes\Action;
use Core\Bouncer\Gate\Models\ActionPermission;
use Core\Bouncer\Gate\Models\ActionRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * Action Gate Service - whitelist-based request authorization.
 *
 * Philosophy: "If it wasn't trained, it doesn't exist."
 *
 * Every controller action must be explicitly permitted. Unknown actions are
 * blocked in production or prompt for approval in training mode.
 *
 * ## Integration Flow
 *
 * ```
 * Request -> ActionGateMiddleware -> ActionGateService::check() -> Controller
 *                                          |
 *                                          v
 *                                    ActionPermission
 *                                    (allowed/denied)
 * ```
 *
 * ## Action Resolution Priority
 *
 * 1. Route action (via `->action('name')` macro)
 * 2. Controller method attribute (`#[Action('name')]`)
 * 3. Auto-resolved from controller@method
 */
class ActionGateService
{
    /**
     * Result of permission check.
     */
    public const RESULT_ALLOWED = 'allowed';

    public const RESULT_DENIED = 'denied';

    public const RESULT_TRAINING = 'training';

    /**
     * Cache of resolved action names.
     *
     * @var array<string, array{action: string, scope: string|null}>
     */
    protected array $actionCache = [];

    /**
     * Check if an action is permitted.
     *
     * @return array{result: string, action: string, scope: string|null}
     */
    public function check(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return $this->denied('unknown', null);
        }

        // Resolve action name and scope
        $resolved = $this->resolveAction($route);
        $action = $resolved['action'];
        $scope = $resolved['scope'];

        // Determine guard and role
        $guard = $this->resolveGuard($route);
        $role = $this->resolveRole($request);

        // Check permission
        $allowed = ActionPermission::isAllowed($action, $guard, $role, $scope);

        // Log the request
        $status = $allowed
            ? ActionRequest::STATUS_ALLOWED
            : ($this->isTrainingMode() ? ActionRequest::STATUS_PENDING : ActionRequest::STATUS_DENIED);

        ActionRequest::log(
            method: $request->method(),
            route: $request->path(),
            action: $action,
            guard: $guard,
            status: $status,
            scope: $scope,
            role: $role,
            userId: $request->user()?->id,
            ipAddress: $request->ip(),
        );

        if ($allowed) {
            return $this->allowed($action, $scope);
        }

        if ($this->isTrainingMode()) {
            return $this->training($action, $scope);
        }

        return $this->denied($action, $scope);
    }

    /**
     * Allow an action (create permission).
     */
    public function allow(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null,
        ?string $route = null,
        ?int $trainedBy = null
    ): ActionPermission {
        return ActionPermission::train($action, $guard, $role, $scope, $route, $trainedBy);
    }

    /**
     * Deny an action (revoke permission).
     */
    public function deny(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null
    ): bool {
        return ActionPermission::revoke($action, $guard, $role, $scope);
    }

    /**
     * Check if training mode is enabled.
     */
    public function isTrainingMode(): bool
    {
        return (bool) config('core.bouncer.training_mode', false);
    }

    /**
     * Resolve the action name for a route.
     *
     * @return array{action: string, scope: string|null}
     */
    public function resolveAction(Route $route): array
    {
        $cacheKey = $route->getName() ?? $route->uri();

        if (isset($this->actionCache[$cacheKey])) {
            return $this->actionCache[$cacheKey];
        }

        // 1. Check for explicit route action
        $routeAction = $route->getAction('bouncer_action');
        if ($routeAction) {
            $result = [
                'action' => $routeAction,
                'scope' => $route->getAction('bouncer_scope'),
            ];
            $this->actionCache[$cacheKey] = $result;

            return $result;
        }

        // 2. Check controller method attribute (requires container)
        try {
            $controller = $route->getController();
            $method = $route->getActionMethod();

            if ($controller !== null && $method !== 'Closure') {
                $attributeResult = $this->resolveFromAttribute($controller, $method);
                if ($attributeResult !== null) {
                    $this->actionCache[$cacheKey] = $attributeResult;

                    return $attributeResult;
                }
            }
        } catch (\Throwable) {
            // Container not available or controller doesn't exist
            // Fall through to auto-resolution
        }

        // 3. Auto-resolve from controller@method
        $result = [
            'action' => $this->autoResolveAction($route),
            'scope' => null,
        ];
        $this->actionCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Resolve action from controller/method attribute.
     *
     * @return array{action: string, scope: string|null}|null
     */
    protected function resolveFromAttribute(object $controller, string $method): ?array
    {
        try {
            $reflection = new ReflectionMethod($controller, $method);
            $attributes = $reflection->getAttributes(Action::class);

            if (empty($attributes)) {
                // Check class-level attribute as fallback
                $classReflection = new ReflectionClass($controller);
                $attributes = $classReflection->getAttributes(Action::class);
            }

            if (! empty($attributes)) {
                /** @var Action $action */
                $action = $attributes[0]->newInstance();

                return [
                    'action' => $action->name,
                    'scope' => $action->scope,
                ];
            }
        } catch (\ReflectionException) {
            // Fall through to auto-resolution
        }

        return null;
    }

    /**
     * Auto-resolve action name from controller and method.
     *
     * Examples:
     * - ProductController@store -> product.store
     * - Admin\UserController@index -> admin.user.index
     * - Api\V1\OrderController@show -> api.v1.order.show
     */
    protected function autoResolveAction(Route $route): string
    {
        $uses = $route->getAction('uses');

        if (is_string($uses) && str_contains($uses, '@')) {
            [$controllerClass, $method] = explode('@', $uses);

            // Remove 'Controller' suffix and convert to dot notation
            $parts = explode('\\', $controllerClass);
            $parts = array_map(function ($part) {
                // Remove 'Controller' suffix
                if (str_ends_with($part, 'Controller')) {
                    $part = substr($part, 0, -10);
                }

                // Convert PascalCase to snake_case, then to kebab-case dots
                return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $part));
            }, $parts);

            // Filter out common namespace prefixes
            $parts = array_filter($parts, fn ($p) => ! in_array($p, ['app', 'http', 'controllers']));

            $parts[] = strtolower($method);

            return implode('.', array_values($parts));
        }

        // Fallback for closures or invokable controllers
        return 'route.'.($route->getName() ?? $route->uri());
    }

    /**
     * Resolve the guard from route middleware.
     */
    protected function resolveGuard(Route $route): string
    {
        $middleware = $route->gatherMiddleware();

        foreach (['admin', 'api', 'client', 'web'] as $guard) {
            if (in_array($guard, $middleware)) {
                return $guard;
            }
        }

        return 'web';
    }

    /**
     * Resolve the user's role.
     */
    protected function resolveRole(Request $request): ?string
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        // Common role resolution strategies
        if (method_exists($user, 'getRole')) {
            return $user->getRole();
        }

        if (method_exists($user, 'role') && is_callable([$user, 'role'])) {
            $role = $user->role();

            return is_object($role) ? ($role->name ?? null) : $role;
        }

        if (property_exists($user, 'role')) {
            return $user->role;
        }

        return null;
    }

    /**
     * Build an allowed result.
     *
     * @return array{result: string, action: string, scope: string|null}
     */
    protected function allowed(string $action, ?string $scope): array
    {
        return [
            'result' => self::RESULT_ALLOWED,
            'action' => $action,
            'scope' => $scope,
        ];
    }

    /**
     * Build a denied result.
     *
     * @return array{result: string, action: string, scope: string|null}
     */
    protected function denied(string $action, ?string $scope): array
    {
        return [
            'result' => self::RESULT_DENIED,
            'action' => $action,
            'scope' => $scope,
        ];
    }

    /**
     * Build a training mode result.
     *
     * @return array{result: string, action: string, scope: string|null}
     */
    protected function training(string $action, ?string $scope): array
    {
        return [
            'result' => self::RESULT_TRAINING,
            'action' => $action,
            'scope' => $scope,
        ];
    }

    /**
     * Clear the action resolution cache.
     */
    public function clearCache(): void
    {
        $this->actionCache = [];
    }
}
