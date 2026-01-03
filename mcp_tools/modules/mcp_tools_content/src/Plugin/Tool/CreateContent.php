<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_content",
 *   label = @Translation("Create Content"),
 *   description = @Translation("Create new content (node) of a specified type."),
 *   category = "content",
 * )
 */
class CreateContent extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ContentService $contentService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    return $instance;
  }

  public function execute(array $input = []): array {
    $type = $input['type'] ?? '';
    $title = $input['title'] ?? '';

    if (empty($type) || empty($title)) {
      return ['success' => FALSE, 'error' => 'Both type and title are required.'];
    }

    $options = [];
    if (isset($input['status'])) {
      $options['status'] = (bool) $input['status'];
    }

    return $this->contentService->createContent($type, $title, $input['fields'] ?? [], $options);
  }

  public function getInputDefinition(): array {
    return [
      'type' => ['type' => 'string', 'label' => 'Content Type', 'required' => TRUE],
      'title' => ['type' => 'string', 'label' => 'Title', 'required' => TRUE],
      'fields' => ['type' => 'object', 'label' => 'Fields', 'required' => FALSE],
      'status' => ['type' => 'boolean', 'label' => 'Published', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'nid' => ['type' => 'integer', 'label' => 'Node ID'],
      'uuid' => ['type' => 'string', 'label' => 'UUID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
