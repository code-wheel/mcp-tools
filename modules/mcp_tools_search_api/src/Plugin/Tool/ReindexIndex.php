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
 * Tool for marking all items for reindexing.
 *
 * @Tool(
 *   id = "mcp_search_api_reindex",
 *   label = @Translation("Reindex Search Index"),
 *   description = @Translation("Mark all items in a search index for reindexing."),
 *   category = "search_api",
 * )
 */
class ReindexIndex extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('reindex', 'search_api_index');
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

    $result = $this->searchApiService->reindexIndex($id);

    if ($result['success']) {
      $this->auditLogger->log('reindex', 'search_api_index', $id, [
        'total_items' => $result['total_items'] ?? 0,
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
        'description' => 'The machine name of the search index to reindex.',
        'required' => TRUE,
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
      'total_items' => [
        'type' => 'integer',
        'label' => 'Total items to reindex',
      ],
    ];
  }

}
