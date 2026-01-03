<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_enable_view",
 *   label = @Translation("Enable View"),
 *   description = @Translation("Enable a disabled view."),
 *   category = "views",
 * )
 */
class EnableView extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->viewsService->setViewStatus($id, TRUE);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View Machine Name', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'View ID'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
