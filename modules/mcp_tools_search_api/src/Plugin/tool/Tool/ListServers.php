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
  id: 'mcp_search_api_list_servers',
  label: new TranslatableMarkup('List Search Servers'),
  description: new TranslatableMarkup('List all Search API servers.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total servers'),
      description: new TranslatableMarkup(''),
    ),
    'servers' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('List of search servers'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListServers extends McpToolsToolBase {

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
    return $this->searchApiService->listServers();
  }

  

  

}
