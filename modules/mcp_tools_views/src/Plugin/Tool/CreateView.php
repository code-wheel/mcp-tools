<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_view",
 *   label = @Translation("Create View"),
 *   description = @Translation("Create a new view with optional page and block displays."),
 *   category = "views",
 * )
 */
class CreateView extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $baseTable = $input['base_table'] ?? 'node_field_data';

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
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }
    if (!empty($input['sort'])) {
      $options['sort'] = $input['sort'];
    }

    return $this->viewsService->createView($id, $label, $baseTable, $options);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE],
      'base_table' => ['type' => 'string', 'label' => 'Base Table', 'required' => FALSE],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
      'page_path' => ['type' => 'string', 'label' => 'Page Path', 'required' => FALSE],
      'block' => ['type' => 'boolean', 'label' => 'Create Block Display', 'required' => FALSE],
      'items_per_page' => ['type' => 'integer', 'label' => 'Items Per Page', 'required' => FALSE],
      'sort' => ['type' => 'string', 'label' => 'Sort Option', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View ID'],
      'label' => ['type' => 'string', 'label' => 'View Label'],
      'base_table' => ['type' => 'string', 'label' => 'Base Table'],
      'displays' => ['type' => 'array', 'label' => 'Display IDs'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
