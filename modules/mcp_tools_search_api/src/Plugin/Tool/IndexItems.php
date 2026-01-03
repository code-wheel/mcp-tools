<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_search_api\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_search_api\Service\SearchApiService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for indexing a batch of items.
 *
 * @Tool(
 *   id = "mcp_search_api_index",
 *   label = @Translation("Index Items"),
 *   description = @Translation("Index a batch of items on a search index."),
 *   category = "search_api",
 * )
 */
class IndexItems extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SearchApiService $searchApiService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->searchApiService = $container->get('mcp_tools_search_api.search_api_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('index', 'search_api_index');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $id = $input['id'] ?? '';
    if (empty($id)) {
      return [
        'success' => FALSE,
        'error' => 'id is required.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    $limit = (int) ($input['limit'] ?? 100);
    if ($limit <= 0) {
      $limit = 100;
    }

    $result = $this->searchApiService->indexItems($id, $limit);

    if ($result['success']) {
      $this->auditLogger->log('index', 'search_api_index', $id, [
        'items_indexed' => $result['items_indexed'] ?? 0,
        'remaining' => $result['remaining'] ?? 0,
      ]);
    }

    return $result;
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
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of items to index (default: 100).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'success' => [
        'type' => 'boolean',
        'label' => 'Success status',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result message',
      ],
      'items_indexed' => [
        'type' => 'integer',
        'label' => 'Number of items indexed',
      ],
      'remaining' => [
        'type' => 'integer',
        'label' => 'Remaining items to index',
      ],
    ];
  }

}
