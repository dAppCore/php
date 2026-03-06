<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

/**
 * Fired when MCP protocol routes are being registered.
 *
 * Modules listen to this event to register their MCP HTTP endpoints
 * for AI agent access via the mcp.* domain.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireMcpRoutes()` when the MCP frontage
 * initialises, typically for requests to the MCP domain.
 *
 * ## Middleware
 *
 * Routes registered through this event are automatically wrapped with
 * the 'mcp' middleware group (stateless, rate limiting).
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     McpRoutesRegistering::class => 'onMcpRoutes',
 * ];
 *
 * public function onMcpRoutes(McpRoutesRegistering $event): void
 * {
 *     $event->routes(fn () => Route::domain(config('mcp.domain'))
 *         ->middleware(McpApiKeyAuth::class)
 *         ->group(function () {
 *             Route::post('tools/call', [McpApiController::class, 'callTool']);
 *         })
 *     );
 * }
 * ```
 *
 * @see ApiRoutesRegistering For REST API routes
 * @see McpToolsRegistering For registering MCP tool handlers
 */
class McpRoutesRegistering extends LifecycleEvent
{
    //
}
