<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting metatag defaults.
 *
 * @Tool(
 *   id = "mcp_metatag_get_defaults",
 *   label = @Translation("Get Metatag Defaults"),
 *   description = @Translation("Get default metatag configuration, optionally filtered by entity type."),
 *   category = "metatag",
 * )
 */
class GetMetatagDefaults extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $type = $input['type'] ?? NULL;
    return $this->metatagService->getMetatagDefaults($type);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'Optional entity type to get defaults for (e.g., "node", "taxonomy_term", "article").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Defaults'],
      'defaults' => ['type' => 'array', 'label' => 'Metatag Defaults'],
    ];
  }

}
