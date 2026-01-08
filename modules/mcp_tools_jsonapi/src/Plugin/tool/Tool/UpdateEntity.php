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
 * Update an existing entity via JSON:API.
 */
#[Tool(
  id: 'mcp_jsonapi_update_entity',
  label: new TranslatableMarkup('JSON:API Update Entity'),
  description: new TranslatableMarkup('Update an existing entity by UUID. Only the specified fields will be modified.'),
  operation: ToolOperation::Write,
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
      description: new TranslatableMarkup('The entity UUID to update. Use mcp_jsonapi_list_entities or mcp_jsonapi_get_entity to find UUIDs.'),
      required: TRUE,
    ),
    'fields' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Field values to update as key-value pairs. Only specified fields will be changed. Example: {"title": "New Title", "status": 1}'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The updated entity type ID.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The updated entity bundle.'),
    ),
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The updated entity numeric ID.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The updated entity UUID.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class UpdateEntity extends McpToolsToolBase {

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
    $fields = $input['fields'] ?? [];

    if (empty($entityType) || empty($uuid)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and uuid are required.'];
    }

    if (empty($fields)) {
      return ['success' => FALSE, 'error' => 'fields is required and cannot be empty.'];
    }

    return $this->jsonApiService->updateEntity($entityType, $uuid, $fields);
  }

}
