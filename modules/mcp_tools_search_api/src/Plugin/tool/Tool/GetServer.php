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
  id: 'mcp_search_api_get_server',
  label: new TranslatableMarkup('Get Search Server'),
  description: new TranslatableMarkup('Get detailed information about a search server.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Server ID'),
      description: new TranslatableMarkup('The machine name of the search server.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'server' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Server details'),
      description: new TranslatableMarkup('Server configuration: id, label, backend (solr/elasticsearch/db), status, and connection details. Lists indexes using this server.'),
    ),
  ],
)]
class GetServer extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'search_api';


  /**
   * The search api service.
   *
   * @var \Drupal\mcp_tools_search_api\Service\SearchApiService
   */
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

    return $this->searchApiService->getServer($id);
  }

}
