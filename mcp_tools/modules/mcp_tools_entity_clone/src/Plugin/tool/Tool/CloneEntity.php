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
  id: 'mcp_entity_clone_clone',
  label: new TranslatableMarkup('Clone Entity'),
  description: new TranslatableMarkup('Clone a single entity with optional title prefix/suffix and child paragraph handling.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type to clone (e.g., node, media, paragraph)'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The ID of the entity to clone'),
      required: TRUE,
    ),
    'title_prefix' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title Prefix'),
      description: new TranslatableMarkup('Prefix to add to the cloned entity title'),
      required: FALSE,
    ),
    'title_suffix' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title Suffix'),
      description: new TranslatableMarkup('Suffix to add to the cloned entity title (default: " (Clone)")'),
      required: FALSE,
    ),
    'clone_children' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Clone Children'),
      description: new TranslatableMarkup('Whether to clone child paragraphs (default: true)'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type that was cloned.'),
    ),
    'source_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Entity ID'),
      description: new TranslatableMarkup('ID of the original entity.'),
    ),
    'clone_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity ID'),
      description: new TranslatableMarkup('ID of the new cloned entity. Use with GetContent to view.'),
    ),
    'clone_uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity UUID'),
      description: new TranslatableMarkup('UUID of the cloned entity for config references.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Cloned Entity Label'),
      description: new TranslatableMarkup('Title/label of the cloned entity.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class CloneEntity extends McpToolsToolBase {

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

    $options = [];
    if (isset($input['title_prefix'])) {
      $options['title_prefix'] = $input['title_prefix'];
    }
    if (isset($input['title_suffix'])) {
      $options['title_suffix'] = $input['title_suffix'];
    }
    if (isset($input['clone_children'])) {
      $options['clone_children'] = (bool) $input['clone_children'];
    }

    return $this->entityCloneService->cloneEntity($entityType, $entityId, $options);
  }


}
