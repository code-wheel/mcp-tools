<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\TaxonomyService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting taxonomy terms.
 *
 * @Tool(
 *   id = "mcp_tools_get_terms",
 *   label = @Translation("Get Terms"),
 *   description = @Translation("Get taxonomy terms from a specific vocabulary."),
 *   category = "content",
 * )
 */
class GetTerms extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $vid = $input['vocabulary'] ?? '';
    $limit = min($input['limit'] ?? 100, 500);
    $hierarchical = $input['hierarchical'] ?? FALSE;

    if (empty($vid)) {
      return [
        'success' => FALSE,
        'error' => 'Vocabulary ID is required.',
      ];
    }

    $data = $this->taxonomy->getTerms($vid, $limit, $hierarchical);

    if (isset($data['error'])) {
      return [
        'success' => FALSE,
        'error' => $data['error'],
      ];
    }

    return [
      'success' => TRUE,
      'data' => $data,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'vocabulary' => [
        'type' => 'string',
        'label' => 'Vocabulary ID',
        'description' => 'The vocabulary machine name (e.g., "tags", "categories").',
        'required' => TRUE,
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum terms to return. Max 500.',
        'required' => FALSE,
        'default' => 100,
      ],
      'hierarchical' => [
        'type' => 'boolean',
        'label' => 'Hierarchical',
        'description' => 'If true, return terms in a nested hierarchy structure.',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'vocabulary' => [
        'type' => 'string',
        'label' => 'Vocabulary ID',
      ],
      'vocabulary_label' => [
        'type' => 'string',
        'label' => 'Vocabulary Label',
      ],
      'total' => [
        'type' => 'integer',
        'label' => 'Total Terms',
      ],
      'terms' => [
        'type' => 'list',
        'label' => 'Terms',
      ],
    ];
  }

}
