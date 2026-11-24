<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\ContentAnalysisService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_list_content_types',
  label: new TranslatableMarkup('List Content Types'),
  description: new TranslatableMarkup('List all content types on the Drupal site with their fields and configuration. With field, only the types that have that field.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'field' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field'),
      description: new TranslatableMarkup('Only the content types that have this field, by its machine name (e.g., "field_price").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total_types' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Content Types'),
      description: new TranslatableMarkup('Number of content types defined on the site.'),
    ),
    'types' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Types'),
      description: new TranslatableMarkup('Array of content types with id (machine name), label, description, field_count, content_count (the content the user may see) and fields. Use the id when creating content.'),
    ),
  ],
)]
class ListContentTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content analysis service.
   */
  protected ContentAnalysisService $contentAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentAnalysis = $container->get('mcp_tools.content_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->contentAnalysis->getContentTypes(($input['field'] ?? NULL) ?: NULL),
    ];
  }

}
