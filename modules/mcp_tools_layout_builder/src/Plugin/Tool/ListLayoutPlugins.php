<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_list_plugins",
 *   label = @Translation("List Layout Plugins"),
 *   description = @Translation("List available layout plugins (one-column, two-column, etc.)."),
 *   category = "layout_builder",
 * )
 */
class ListLayoutPlugins extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->layoutBuilderService->listLayoutPlugins();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'layouts' => ['type' => 'array', 'label' => 'Available Layouts'],
      'count' => ['type' => 'integer', 'label' => 'Total Count'],
    ];
  }

}
