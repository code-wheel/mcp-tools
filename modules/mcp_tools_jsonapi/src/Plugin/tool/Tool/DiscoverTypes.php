<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_jsonapi\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_jsonapi\Service\JsonApiService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discover available entity types exposed via JSON:API.
 */
#[Tool(
  id: 'mcp_jsonapi_discover_types',
  label: new TranslatableMarkup('JSON:API Discover Types'),
  description: new TranslatableMarkup('Discover all entity types and bundles available via JSON:API. Use this to find what entity types can be queried, created, or modified.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'types' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Entity Types'),
      description: new TranslatableMarkup('List of available entity types with metadata (entity_type, bundle, resource_type, label).'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Count'),
      description: new TranslatableMarkup('Total number of available entity type/bundle combinations.'),
    ),
  ],
)]
class DiscoverTypes extends McpToolsToolBase {

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
    return $this->jsonApiService->discoverTypes();
  }

}
