<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_add_section",
 *   label = @Translation("Add Section"),
 *   description = @Translation("Add a layout section to the default layout."),
 *   category = "layout_builder",
 * )
 */
class AddSection extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $layoutId = $input['layout_id'] ?? '';
    $delta = $input['delta'] ?? 0;

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($layoutId)) {
      return ['success' => FALSE, 'error' => 'Layout ID is required. Use mcp_layout_list_plugins to see available layouts.'];
    }

    return $this->layoutBuilderService->addSection($entityType, $bundle, $layoutId, (int) $delta);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle/Content Type', 'required' => TRUE],
      'layout_id' => ['type' => 'string', 'label' => 'Layout Plugin ID', 'required' => TRUE],
      'delta' => ['type' => 'integer', 'label' => 'Position (Delta)', 'required' => FALSE, 'default' => 0],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'layout_id' => ['type' => 'string', 'label' => 'Layout ID'],
      'delta' => ['type' => 'integer', 'label' => 'Section Delta'],
      'section_count' => ['type' => 'integer', 'label' => 'Total Sections'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
