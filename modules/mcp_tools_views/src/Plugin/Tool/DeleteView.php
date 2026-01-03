<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_delete_view",
 *   label = @Translation("Delete View"),
 *   description = @Translation("Delete a view. Core views are protected."),
 *   category = "views",
 * )
 */
class DeleteView extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'View id is required.'];
    }

    return $this->viewsService->deleteView($id);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View Machine Name', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
