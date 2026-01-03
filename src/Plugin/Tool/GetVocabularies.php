<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\TaxonomyService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting taxonomy vocabularies.
 *
 * @Tool(
 *   id = "mcp_tools_get_vocabularies",
 *   label = @Translation("Get Vocabularies"),
 *   description = @Translation("Get all taxonomy vocabularies with term counts."),
 *   category = "content",
 * )
 */
class GetVocabularies extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected TaxonomyService $taxonomy;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->taxonomy = $container->get('mcp_tools.taxonomy');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->taxonomy->getVocabularies(),
    ];
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
      'total_vocabularies' => [
        'type' => 'integer',
        'label' => 'Total Vocabularies',
      ],
      'vocabularies' => [
        'type' => 'list',
        'label' => 'Vocabularies',
      ],
    ];
  }

}
