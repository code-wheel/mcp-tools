<?php

/**
 * @file
 * Hooks provided by the MCP Tools module.
 */

declare(strict_types=1);

/**
 * Declare MCP components without Tool API plugins.
 *
 * Modules can return tools, resources, resource templates, and prompts in a
 * simple array structure. Each entry should match the corresponding Server
 * builder signature (handler callable plus metadata).
 *
 * @return array<string, array<int, array<string, mixed>>>
 *   Component definitions keyed by component type.
 */
function hook_mcp_tools_components(): array {
  return [
    'tools' => [
      [
        'name' => 'my_module/ping',
        'description' => 'Return a simple ping response.',
        'handler' => function (): \Mcp\Schema\Result\CallToolResult {
          return new \Mcp\Schema\Result\CallToolResult([
            new \Mcp\Schema\Content\TextContent('pong'),
          ]);
        },
        'inputSchema' => [
          'type' => 'object',
          'properties' => new \stdClass(),
        ],
        // Optional public marker for auto-discovery filters.
        'public' => TRUE,
      ],
    ],
  ];
}
