<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\tool\Tool;

use Drupal\mcp_tools_migration\Service\MigrationService;
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
  id: 'mcp_migration_field_mapping',
  label: new TranslatableMarkup('Get Field Mapping'),
  description: new TranslatableMarkup('Get required and optional fields for a content type to help prepare import data.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The machine name of the content type to get field information for.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type machine name.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('The human-readable content type label.'),
    ),
    'required' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Required Fields'),
      description: new TranslatableMarkup('Required fields with label, type, and description.'),
    ),
    'optional' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Optional Fields'),
      description: new TranslatableMarkup('Optional fields with label, type, and description.'),
    ),
  ],
)]
class GetFieldMapping extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'migration';


  protected MigrationService $migrationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->migrationService = $container->get('mcp_tools_migration.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $contentType = $input['content_type'] ?? '';

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'Content type is required.'];
    }

    return $this->migrationService->getFieldMapping($contentType);
  }

  

  

}
