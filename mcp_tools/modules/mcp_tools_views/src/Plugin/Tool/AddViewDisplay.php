<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_add_view_display",
 *   label = @Translation("Add View Display"),
 *   description = @Translation("Add a display (page, block, feed) to an existing view."),
 *   category = "views",
 * )
 */
class AddViewDisplay extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  public function execute(array $input = []): array {
    $viewId = $input['view_id'] ?? '';
    $displayType = $input['display_type'] ?? '';

    if (empty($viewId) || empty($displayType)) {
      return ['success' => FALSE, 'error' => 'Both view_id and display_type are required.'];
    }

    $options = [];
    if (!empty($input['path'])) {
      $options['path'] = $input['path'];
    }
    if (!empty($input['title'])) {
      $options['title'] = $input['title'];
    }
    if (!empty($input['items_per_page'])) {
      $options['items_per_page'] = (int) $input['items_per_page'];
    }

    return $this->viewsService->addDisplay($viewId, $displayType, $options);
  }

  public function getInputDefinition(): array {
    return [
      'view_id' => ['type' => 'string', 'label' => 'View Machine Name', 'required' => TRUE],
      'display_type' => ['type' => 'string', 'label' => 'Display Type (page, block, feed)', 'required' => TRUE],
      'path' => ['type' => 'string', 'label' => 'Page Path (for page displays)', 'required' => FALSE],
      'title' => ['type' => 'string', 'label' => 'Display Title', 'required' => FALSE],
      'items_per_page' => ['type' => 'integer', 'label' => 'Items Per Page', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'view_id' => ['type' => 'string', 'label' => 'View ID'],
      'display_id' => ['type' => 'string', 'label' => 'Display ID'],
      'display_type' => ['type' => 'string', 'label' => 'Display Type'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
