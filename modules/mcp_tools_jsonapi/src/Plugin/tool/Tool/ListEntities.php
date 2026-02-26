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
 * List entities with optional filters.
 */
#[Tool(
  id: 'mcp_jsonapi_list_entities',
  label: new TranslatableMarkup('JSON:API List Entities'),
  description: new TranslatableMarkup('List entities of a given type with optional filtering and pagination. Use DiscoverTypes to find available entity types.'),
  operation: ToolOperation::Read,
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
      description: new TranslatableMarkup('Optional bundle/type filter (e.g., "article" for nodes). Limits results to this bundle only.'),
      required: FALSE,
    ),
    'filters' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Filters'),
      description: new TranslatableMarkup('Optional field filters as key-value pairs (e.g., {"status": 1} for published nodes).'),
      required: FALSE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum items to return (default 25, max configured in settings).'),
      required: FALSE,
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Pagination offset (default 0). Use with limit for paging through results.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'items' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Items'),
      description: new TranslatableMarkup('List of matching entities.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total'),
      description: new TranslatableMarkup('Total number of matching entities (before pagination).'),
    ),
    'limit' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Items per page used.'),
    ),
    'offset' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Current offset.'),
    ),
    'has_more' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has More'),
      description: new TranslatableMarkup('Whether more items exist beyond current page.'),
    ),
  ],
)]
class ListEntities extends McpToolsToolBase {

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

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'entity_type is required.'];
    }

    $bundle = $input['bundle'] ?? NULL;
    $filters = $input['filters'] ?? [];
    $limit = (int) ($input['limit'] ?? 25);
    $offset = (int) ($input['offset'] ?? 0);

    return $this->jsonApiService->listEntities($entityType, $bundle, $filters, $limit, $offset);
  }

}
