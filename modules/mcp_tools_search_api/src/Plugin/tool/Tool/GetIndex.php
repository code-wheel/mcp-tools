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
  id: 'mcp_search_api_get_index',
  label: new TranslatableMarkup('Get Search Index'),
  description: new TranslatableMarkup('Get detailed information about a search index including fields, datasources, and status.'),
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
    'index' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Index details'),
      description: new TranslatableMarkup('Index configuration: id, label, server, fields, datasources, and indexing status. Use with IndexItems, ReindexIndex, or ClearIndex.'),
    ),
  ],
)]
class GetIndex extends McpToolsToolBase {

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

    return $this->searchApiService->getIndex($id);
  }

  

  

}
