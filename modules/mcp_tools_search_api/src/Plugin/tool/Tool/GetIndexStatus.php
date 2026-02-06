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
  id: 'mcp_search_api_status',
  label: new TranslatableMarkup('Get Index Status'),
  description: new TranslatableMarkup('Get indexing status for a search index (total, indexed, remaining).'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Index ID'),
      description: new TranslatableMarkup('The machine name of the search index.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'index_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Index ID'),
      description: new TranslatableMarkup('Machine name of the queried search index.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Indexing status'),
      description: new TranslatableMarkup('Status object with total_items, indexed_items, and remaining_items. Use remaining to determine if reindexing needed.'),
    ),
  ],
)]
class GetIndexStatus extends McpToolsToolBase {

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
    $id = $input['id'] ?? '';
    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'id is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    return $this->searchApiService->getIndexStatus($id);
  }

  

  

}
