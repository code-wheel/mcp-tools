<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_remove_section",
 *   label = @Translation("Remove Section"),
 *   description = @Translation("Remove a layout section from the default layout."),
 *   category = "layout_builder",
 * )
 */
class RemoveSection extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $delta = $input['delta'] ?? NULL;

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if ($delta === NULL || $delta === '') {
      return ['success' => FALSE, 'error' => 'Section delta is required.'];
    }

    return $this->layoutBuilderService->removeSection($entityType, $bundle, (int) $delta);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle/Content Type', 'required' => TRUE],
      'delta' => ['type' => 'integer', 'label' => 'Section Delta', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'removed_delta' => ['type' => 'integer', 'label' => 'Removed Section Delta'],
      'section_count' => ['type' => 'integer', 'label' => 'Remaining Sections'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
