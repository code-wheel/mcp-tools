<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_delete_content",
 *   label = @Translation("Delete Content"),
 *   description = @Translation("Permanently delete a node."),
 *   category = "content",
 * )
 */
class DeleteContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->contentService->deleteContent((int) $nid);
  }

  public function getInputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Deleted Node ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
