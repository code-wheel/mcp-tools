<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\tool\Tool;

use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
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
  id: 'mcp_entity_clone_settings',
  label: new TranslatableMarkup('Get Clone Settings'),
  description: new TranslatableMarkup('Get clone settings and reference fields for a specific entity type and bundle.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type (e.g., node, media)'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The bundle/content type machine name (e.g., article, page)'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
    ),
    'settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Clone Settings'),
      description: new TranslatableMarkup(''),
    ),
    'reference_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Reference Fields'),
      description: new TranslatableMarkup(''),
    ),
    'paragraph_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Paragraph Fields'),
      description: new TranslatableMarkup(''),
    ),
    'has_paragraphs' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Paragraphs'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetCloneSettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'entity_clone';


  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? '';

    if (empty($entityType) || empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and bundle are required.'];
    }

    return $this->entityCloneService->getCloneSettings($entityType, $bundle);
  }


}
