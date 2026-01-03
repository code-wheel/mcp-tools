<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_layout_remove_block",
 *   label = @Translation("Remove Block"),
 *   description = @Translation("Remove a block from the layout by its UUID."),
 *   category = "layout_builder",
 * )
 */
class RemoveBlock extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected LayoutBuilderService $layoutBuilderService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->layoutBuilderService = $container->get('mcp_tools_layout_builder.layout_builder');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $blockUuid = $input['block_uuid'] ?? '';

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle (content type) is required.'];
    }

    if (empty($blockUuid)) {
      return ['success' => FALSE, 'error' => 'Block UUID is required. Use mcp_layout_get to see block UUIDs.'];
    }

    return $this->layoutBuilderService->removeBlock($entityType, $bundle, $blockUuid);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle/Content Type', 'required' => TRUE],
      'block_uuid' => ['type' => 'string', 'label' => 'Block Component UUID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'removed_block_uuid' => ['type' => 'string', 'label' => 'Removed Block UUID'],
      'section_delta' => ['type' => 'integer', 'label' => 'Section Delta'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
