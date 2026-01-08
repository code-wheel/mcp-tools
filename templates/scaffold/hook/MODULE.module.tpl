<?php

declare(strict_types=1);

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
{{ prompt_uses }}

/**
 * Implements hook_mcp_tools_components().
 */
function {{ machine_name }}_mcp_tools_components(): array {
  return [
    'tools' => [
      [
        'name' => '{{ tool_name }}',
        'description' => 'Return a simple ping response.',
        'handler' => function (): CallToolResult {
          return new CallToolResult([new TextContent('pong')]);
        },
        'inputSchema' => [
          'type' => 'object',
          'properties' => new \stdClass(),
        ],
        'public' => TRUE,
      ],
    ],
    'resources' => [
{{ resources_items }}    ],
    'resource_templates' => [
{{ resource_templates_items }}    ],
    'prompts' => [
{{ prompts_items }}    ],
  ];
}
