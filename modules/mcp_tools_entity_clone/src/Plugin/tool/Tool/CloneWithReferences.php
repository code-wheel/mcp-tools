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
  id: 'mcp_entity_clone_with_refs',
  label: new TranslatableMarkup('Clone Entity with References'),
  description: new TranslatableMarkup('Clone an entity and specified referenced entities, updating references in the clone.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type to clone (e.g., node, media)'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The ID of the entity to clone'),
      required: TRUE,
    ),
    'reference_fields' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Reference Fields'),
      description: new TranslatableMarkup('List of reference field names to also clone (e.g., ["field_related_articles", "field_author"])'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'source_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Entity ID'),
      description: new TranslatableMarkup(''),
    ),
    'clone_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity ID'),
      description: new TranslatableMarkup(''),
    ),
    'clone_uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity UUID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity Label'),
      description: new TranslatableMarkup(''),
    ),
    'cloned_references' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cloned Referenced Entities'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CloneWithReferences extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'entity_clone';


  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $entityId = $input['entity_id'] ?? '';

    if (empty($entityType) || empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and entity_id are required.'];
    }

    $referenceFields = $input['reference_fields'] ?? [];
    if (!is_array($referenceFields)) {
      $referenceFields = [$referenceFields];
    }

    return $this->entityCloneService->cloneWithReferences($entityType, $entityId, $referenceFields);
  }


}
