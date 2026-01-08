<?php

declare(strict_types=1);

namespace Drupal\{{ machine_name }}\Mcp;

use Drupal\mcp_tools\Mcp\Component\ComponentProviderInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
{{ prompt_uses }}

final class {{ provider_class }} implements ComponentProviderInterface {

  public function getTools(): array {
    return [
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
    ];
  }

  public function getResources(): array {
    return [
{{ resources_items }}    ];
  }

  public function getResourceTemplates(): array {
    return [
{{ resource_templates_items }}    ];
  }

  public function getPrompts(): array {
    return [
{{ prompts_items }}    ];
  }

}
