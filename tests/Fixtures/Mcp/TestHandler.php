<?php

declare(strict_types=1);

namespace Core\Tests\Fixtures\Mcp;

use Core\Front\Mcp\Contracts\McpToolHandler;
use Core\Front\Mcp\McpContext;

class TestHandler implements McpToolHandler
{
    public static function schema(): array
    {
        return [
            'name' => 'test_tool',
            'description' => 'A test tool',
            'inputSchema' => ['type' => 'object'],
        ];
    }

    public function handle(array $args, McpContext $context): array
    {
        return ['result' => 'test'];
    }
}
