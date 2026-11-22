<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_jsonapi\Plugin\tool\Tool;

use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_jsonapi\Service\JsonApiService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete an entity via JSON:API.
 */
#[Tool(
  id: 'mcp_jsonapi_delete_entity',
  label: new TranslatableMarkup('JSON:API Delete Entity'),
  description: new TranslatableMarkup('Delete an entity by UUID. This action is permanent and cannot be undone.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type machine name (e.g., "node", "taxonomy_term", "media").'),
      required: TRUE,
    ),
    'uuid' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The entity UUID to delete. Use mcp_jsonapi_list_entities or mcp_jsonapi_get_entity to find UUIDs.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'deleted' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Deleted'),
      description: new TranslatableMarkup('Whether the entity was deleted successfully.'),
    ),
    'entity_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The deleted entity type ID.'),
    ),
    'uuid' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The deleted entity UUID.'),
    ),
    'id' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The deleted entity numeric ID.'),
    ),
    'label' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('The deleted entity label.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class DeleteEntity extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'jsonapi';

  /**
   * The json api service.
   *
   * @var \Drupal\mcp_tools_jsonapi\Service\JsonApiService
   */
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

    if (empty($entityType) || empty($uuid)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and uuid are required.'];
    }

    return $this->jsonApiService->deleteEntity($entityType, $uuid);
  }

}
