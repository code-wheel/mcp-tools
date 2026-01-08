<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_jsonapi\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_jsonapi\Service\JsonApiService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get a single entity by UUID.
 */
#[Tool(
  id: 'mcp_jsonapi_get_entity',
  label: new TranslatableMarkup('JSON:API Get Entity'),
  description: new TranslatableMarkup('Retrieve a single entity by its UUID. Use DiscoverTypes to find available entity types.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type machine name (e.g., "node", "taxonomy_term", "media"). Use mcp_jsonapi_discover_types to see available types.'),
      required: TRUE,
    ),
    'uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The entity UUID (universally unique identifier).'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Optional bundle/type filter (e.g., "article" for nodes). If provided, verifies the entity is of this bundle.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type ID.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The entity bundle.'),
    ),
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity numeric ID.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The entity UUID.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('The entity label/title.'),
    ),
    'fields' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Field values for the entity.'),
    ),
  ],
)]
class GetEntity extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'jsonapi';

  protected JsonApiService $jsonApiService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->jsonApiService = $container->get('mcp_tools_jsonapi.service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $uuid = $input['uuid'] ?? '';
    $bundle = $input['bundle'] ?? NULL;

    if (empty($entityType) || empty($uuid)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and uuid are required.'];
    }

    return $this->jsonApiService->getEntity($entityType, $uuid, $bundle);
  }

}
