<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_update_content",
 *   label = @Translation("Update Content"),
 *   description = @Translation("Update an existing node. Creates a new revision."),
 *   category = "content",
 * )
 */
class UpdateContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  public function execute(array $input = []): array {
    $nid = $input['nid'] ?? 0;
    $updates = $input['updates'] ?? [];

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }
    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    return $this->contentService->updateContent((int) $nid, $updates);
  }

  public function getInputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID', 'required' => TRUE],
      'updates' => ['type' => 'object', 'label' => 'Updates', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'revision_id' => ['type' => 'integer', 'label' => 'New Revision ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
