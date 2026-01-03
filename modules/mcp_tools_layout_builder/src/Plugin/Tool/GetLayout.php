<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_get",
 *   label = @Translation("Get Layout"),
 *   description = @Translation("Get the default layout sections for a content type."),
 *   category = "layout_builder",
 * )
 */
class GetLayout extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    return $this->layoutBuilderService->getLayout($entityType, $bundle);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle/Content Type', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'layout_builder_enabled' => ['type' => 'boolean', 'label' => 'Layout Builder Enabled'],
      'allow_custom_layouts' => ['type' => 'boolean', 'label' => 'Allow Custom Layouts'],
      'sections' => ['type' => 'array', 'label' => 'Layout Sections'],
      'section_count' => ['type' => 'integer', 'label' => 'Section Count'],
    ];
  }

}
