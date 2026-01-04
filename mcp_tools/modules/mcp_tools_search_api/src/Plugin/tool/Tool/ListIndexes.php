<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\tool\Tool;

use Drupal\mcp_tools_search_api\Service\SearchApiService;
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
  id: 'mcp_search_api_list_indexes',
  label: new TranslatableMarkup('List Search Indexes'),
  description: new TranslatableMarkup('List all Search API indexes with their status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total indexes'),
      description: new TranslatableMarkup('Total number of search indexes configured in the system.'),
    ),
    'indexes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('List of search indexes'),
      description: new TranslatableMarkup('Array of index objects with id, label, server, status, and indexing progress. Use id with GetIndex for full details.'),
    ),
  ],
)]
class ListIndexes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'search_api';


  protected SearchApiService $searchApiService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->searchApiService = $container->get('mcp_tools_search_api.search_api_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->searchApiService->listIndexes();
  }

  

  

}
