<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing available metatag groups.
 *
 * @Tool(
 *   id = "mcp_metatag_list_groups",
 *   label = @Translation("List Metatag Groups"),
 *   description = @Translation("List all available metatag groups (basic, open_graph, twitter_cards, etc.)."),
 *   category = "metatag",
 * )
 */
class ListMetatagGroups extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->metatagService->listMetatagGroups();
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
      'total' => ['type' => 'integer', 'label' => 'Total Groups'],
      'groups' => ['type' => 'array', 'label' => 'Metatag Groups'],
    ];
  }

}
