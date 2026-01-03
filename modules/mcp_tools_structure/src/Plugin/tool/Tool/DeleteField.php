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
  id: 'mcp_structure_delete_field',
  label: new TranslatableMarkup('Delete Field'),
  description: new TranslatableMarkup('Delete a field from an entity bundle. Data will be lost!'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
      required: FALSE,
      default_value: 'node',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Field Name'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class DeleteField extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected FieldService $fieldService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';

    if (empty($bundle) || empty($fieldName)) {
      return ['success' => FALSE, 'error' => 'bundle and field_name are required.'];
    }

    return $this->fieldService->deleteField($entityType, $bundle, $fieldName);
  }


}
