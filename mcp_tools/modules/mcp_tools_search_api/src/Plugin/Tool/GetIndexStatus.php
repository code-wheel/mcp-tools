<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting search index status.
 *
 * @Tool(
 *   id = "mcp_search_api_status",
 *   label = @Translation("Get Index Status"),
 *   description = @Translation("Get indexing status for a search index (total, indexed, remaining)."),
 *   category = "search_api",
 * )
 */
class GetIndexStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SearchApiService $searchApiService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->searchApiService = $container->get('mcp_tools_search_api.search_api_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Index ID',
        'description' => 'The machine name of the search index.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'index_id' => [
        'type' => 'string',
        'label' => 'Index ID',
      ],
      'status' => [
        'type' => 'object',
        'label' => 'Indexing status',
      ],
    ];
  }

}
