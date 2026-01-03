<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing search indexes.
 *
 * @Tool(
 *   id = "mcp_search_api_list_indexes",
 *   label = @Translation("List Search Indexes"),
 *   description = @Translation("List all Search API indexes with their status."),
 *   category = "search_api",
 * )
 */
class ListIndexes extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->searchApiService->listIndexes();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => [
        'type' => 'integer',
        'label' => 'Total indexes',
      ],
      'indexes' => [
        'type' => 'list',
        'label' => 'List of search indexes',
      ],
    ];
  }

}
