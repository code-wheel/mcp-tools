<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
  description: new TranslatableMarkup('Deprecated, and removed in a later release: use mcp_tools_list_content_types, which gives the same with the fields of each type, and counts only the content the user may see.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'deprecated' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deprecated'),
      description: new TranslatableMarkup('What to use instead.'),
    ),
    'types' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Types'),
      description: new TranslatableMarkup('Array of content types with id, label, description, field_count, and content_count. Use id with mcp_structure_get_content_type for full field details or mcp_create_content.'),
    ),
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Types'),
      description: new TranslatableMarkup('Total number of content types in the system.'),
    ),
  ],
)]
class ListContentTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  /**
   * The content type service.
   *
   * @var \Drupal\mcp_tools_structure\Service\ContentTypeService
   */
  protected ContentTypeService $contentTypeService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $result = $this->contentTypeService->listContentTypes();
    // Said in the answer too: a model that called it reads this.
    if (!empty($result['success'])) {
      $result['data']['deprecated'] = 'mcp_structure_list_content_types is deprecated: use mcp_tools_list_content_types, which gives the field and content counts and the fields of each type.';
    }
    return $result;
  }

}
