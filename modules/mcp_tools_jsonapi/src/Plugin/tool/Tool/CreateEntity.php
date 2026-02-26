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
 * Create a new entity via JSON:API.
 */
#[Tool(
  id: 'mcp_jsonapi_create_entity',
  label: new TranslatableMarkup('JSON:API Create Entity'),
  description: new TranslatableMarkup('Create a new entity of any exposed type. Use DiscoverTypes to find available entity types and bundles.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type machine name (e.g., "node", "taxonomy_term", "media"). Use mcp_jsonapi_discover_types to see available types.'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Bundle/type for the entity (e.g., "article" for nodes, "tags" for taxonomy terms).'),
      required: TRUE,
    ),
    'fields' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Field values as key-value pairs. Required fields depend on entity type. For nodes: {"title": "My Title", "body": {"value": "Content", "format": "basic_html"}}'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The created entity type ID.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The created entity bundle.'),
    ),
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The created entity numeric ID.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('The created entity UUID. Use this for updates and deletes.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class CreateEntity extends McpToolsToolBase {

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
    $bundle = $input['bundle'] ?? '';
    $fields = $input['fields'] ?? [];

    if (empty($entityType) || empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and bundle are required.'];
    }

    if (empty($fields)) {
      return ['success' => FALSE, 'error' => 'fields is required and cannot be empty.'];
    }

    return $this->jsonApiService->createEntity($entityType, $bundle, $fields);
  }

}
