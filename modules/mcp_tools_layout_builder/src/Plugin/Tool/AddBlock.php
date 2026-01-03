<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_add_block",
 *   label = @Translation("Add Block"),
 *   description = @Translation("Add a block to a section in the layout."),
 *   category = "layout_builder",
 * )
 */
class AddBlock extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $sectionDelta = $input['section_delta'] ?? 0;
    $region = $input['region'] ?? '';
    $blockId = $input['block_id'] ?? '';

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($region)) {
      return ['success' => FALSE, 'error' => 'Region is required.'];
    }

    if (empty($blockId)) {
      return ['success' => FALSE, 'error' => 'Block ID is required.'];
    }

    return $this->layoutBuilderService->addBlock($entityType, $bundle, (int) $sectionDelta, $region, $blockId);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle/Content Type', 'required' => TRUE],
      'section_delta' => ['type' => 'integer', 'label' => 'Section Delta', 'required' => FALSE, 'default' => 0],
      'region' => ['type' => 'string', 'label' => 'Region', 'required' => TRUE],
      'block_id' => ['type' => 'string', 'label' => 'Block Plugin ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'section_delta' => ['type' => 'integer', 'label' => 'Section Delta'],
      'region' => ['type' => 'string', 'label' => 'Region'],
      'block_id' => ['type' => 'string', 'label' => 'Block ID'],
      'component_uuid' => ['type' => 'string', 'label' => 'Component UUID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
