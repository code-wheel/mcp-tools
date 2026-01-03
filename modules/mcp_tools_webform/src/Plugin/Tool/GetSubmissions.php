<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting webform submissions.
 *
 * @Tool(
 *   id = "mcp_get_webform_submissions",
 *   label = @Translation("Get Webform Submissions"),
 *   description = @Translation("Get submissions for a webform with pagination."),
 *   category = "webform",
 * )
 */
class GetSubmissions extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected WebformService $webformService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->webformService = $container->get('mcp_tools_webform.webform');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $webformId = $input['webform_id'] ?? '';

    if (empty($webformId)) {
      return ['success' => FALSE, 'error' => 'Webform ID is required.'];
    }

    $limit = (int) ($input['limit'] ?? 50);
    $offset = (int) ($input['offset'] ?? 0);

    // Enforce reasonable limits.
    $limit = min(max($limit, 1), 100);
    $offset = max($offset, 0);

    return $this->webformService->getSubmissions($webformId, $limit, $offset);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'webform_id' => ['type' => 'string', 'label' => 'Webform ID', 'required' => TRUE],
      'limit' => ['type' => 'integer', 'label' => 'Limit (max 100)', 'required' => FALSE],
      'offset' => ['type' => 'integer', 'label' => 'Offset', 'required' => FALSE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'webform_id' => ['type' => 'string', 'label' => 'Webform ID'],
      'webform_title' => ['type' => 'string', 'label' => 'Webform Title'],
      'total' => ['type' => 'integer', 'label' => 'Total Submissions'],
      'limit' => ['type' => 'integer', 'label' => 'Limit'],
      'offset' => ['type' => 'integer', 'label' => 'Offset'],
      'submissions' => ['type' => 'list', 'label' => 'Submissions'],
    ];
  }

}
