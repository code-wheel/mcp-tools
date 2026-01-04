<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\FieldService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_structure_list_field_types',
  label: new TranslatableMarkup('List Field Types'),
  description: new TranslatableMarkup('List available field types that can be added to entities.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'types' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Field Types'),
      description: new TranslatableMarkup('Array of field types with id (machine name), label, description, and category. Use id with AddField field_type parameter.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Types'),
      description: new TranslatableMarkup('Number of field types available.'),
    ),
  ],
)]
class ListFieldTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected FieldService $fieldService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->fieldService->getFieldTypes(),
    ];
  }


}
