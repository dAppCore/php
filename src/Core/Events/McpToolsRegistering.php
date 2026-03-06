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
 * Fired when MCP (Model Context Protocol) tools are being registered.
 *
 * Modules listen to this event to register their MCP tool handlers, which
 * expose functionality to AI assistants and LLM-powered applications.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireMcpTools()` when:
 * - MCP server starts up (stdio transport for CLI usage)
 * - MCP routes are accessed (HTTP transport for web-based integration)
 *
 * ## Handler Requirements
 *
 * Each handler class must implement `McpToolHandler` interface (from php-mcp).
 * Handlers define the tools, their input schemas, and execution logic.
 * Validation is performed by the MCP package at runtime.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     McpToolsRegistering::class => 'onMcp',
 * ];
 *
 * public function onMcp(McpToolsRegistering $event): void
 * {
 *     $event->handler(ProductSearchHandler::class);
 *     $event->handler(InventoryQueryHandler::class);
 * }
 * ```
 */
class McpToolsRegistering extends LifecycleEvent
{
    /** @var array<int, string> Collected MCP tool handler class names */
    protected array $handlers = [];

    /**
     * Register an MCP tool handler class.
     *
     * @param  string  $handlerClass  Fully qualified class name implementing McpToolHandler
     */
    public function handler(string $handlerClass): void
    {
        $this->handlers[] = $handlerClass;
    }

    /**
     * Get all registered handler class names.
     *
     * @return array<int, string>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function handlers(): array
    {
        return $this->handlers;
    }
}
