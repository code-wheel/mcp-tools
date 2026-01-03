<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all available metatag tags.
 *
 * @Tool(
 *   id = "mcp_metatag_list_tags",
 *   label = @Translation("List Available Metatag Tags"),
 *   description = @Translation("List all available metatag tags with their descriptions and group assignments."),
 *   category = "metatag",
 * )
 */
class ListAvailableTags extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected MetatagService $metatagService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->metatagService = $container->get('mcp_tools_metatag.metatag');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return $this->metatagService->listAvailableTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Tags'],
      'by_group' => ['type' => 'array', 'label' => 'Tags Grouped'],
      'all_tags' => ['type' => 'array', 'label' => 'All Tags'],
    ];
  }

}
