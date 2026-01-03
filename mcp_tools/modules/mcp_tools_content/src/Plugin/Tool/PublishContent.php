<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_publish_content",
 *   label = @Translation("Publish/Unpublish Content"),
 *   description = @Translation("Change publish status of content."),
 *   category = "content",
 * )
 */
class PublishContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  public function execute(array $input = []): array {
    $nid = $input['nid'] ?? 0;

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }

    return $this->contentService->setPublishStatus((int) $nid, (bool) ($input['publish'] ?? TRUE));
  }

  public function getInputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID', 'required' => TRUE],
      'publish' => ['type' => 'boolean', 'label' => 'Publish', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID'],
      'status' => ['type' => 'string', 'label' => 'New Status'],
      'changed' => ['type' => 'boolean', 'label' => 'Status Changed'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
