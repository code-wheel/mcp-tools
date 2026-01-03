<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_content_list_view",
 *   label = @Translation("Create Content List View"),
 *   description = @Translation("Create a content listing view with sensible defaults."),
 *   category = "views",
 * )
 */
class CreateContentListView extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    $contentType = $input['content_type'] ?? '';

    $options = [];
    if (!empty($input['description'])) {
      $options['description'] = $input['description'];
    }
    if (!empty($input['page_path'])) {
      $options['page_path'] = $input['page_path'];
    }
    if (!empty($input['block'])) {
      $options['block'] = (bool) $input['block'];
    }
    if (!empty($input['block_items'])) {
      $options['block_items'] = (int) $input['block_items'];
    }
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }
    if (!empty($input['sort'])) {
      $options['sort'] = $input['sort'];
    }
    if (isset($input['show_title'])) {
      $options['show_title'] = (bool) $input['show_title'];
    }
    if (!empty($input['fields'])) {
      $options['fields'] = $input['fields'];
    }

    return $this->viewsService->createContentListView($id, $label, $contentType, $options);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE],
      'content_type' => ['type' => 'string', 'label' => 'Content Type Filter', 'required' => FALSE],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
      'page_path' => ['type' => 'string', 'label' => 'Page Path', 'required' => FALSE],
      'block' => ['type' => 'boolean', 'label' => 'Create Block Display', 'required' => FALSE],
      'block_items' => ['type' => 'integer', 'label' => 'Block Items Count', 'required' => FALSE],
      'items_per_page' => ['type' => 'integer', 'label' => 'Items Per Page', 'required' => FALSE],
      'sort' => ['type' => 'string', 'label' => 'Sort Option', 'required' => FALSE],
      'show_title' => ['type' => 'boolean', 'label' => 'Show Title', 'required' => FALSE],
      'fields' => ['type' => 'array', 'label' => 'Fields to Display', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View ID'],
      'label' => ['type' => 'string', 'label' => 'View Label'],
      'base_table' => ['type' => 'string', 'label' => 'Base Table'],
      'displays' => ['type' => 'array', 'label' => 'Display IDs'],
      'page_path' => ['type' => 'string', 'label' => 'Page Path'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
