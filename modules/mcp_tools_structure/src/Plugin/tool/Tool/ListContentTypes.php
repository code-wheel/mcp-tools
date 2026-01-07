<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin to list all content types.
 */
#[Tool(
  id: 'mcp_structure_list_content_types',
  label: new TranslatableMarkup('List Content Types'),
  description: new TranslatableMarkup('List all content types with field counts and content counts. Use this to discover what content structures exist before creating or querying content.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'types' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Types'),
      description: new TranslatableMarkup('Array of content types with id, label, description, field_count, and content_count. Use id with GetContentType for full field details or CreateContent.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Types'),
      description: new TranslatableMarkup('Total number of content types in the system.'),
    ),
  ],
)]
class ListContentTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected ContentTypeService $contentTypeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->contentTypeService->listContentTypes();
  }

}
