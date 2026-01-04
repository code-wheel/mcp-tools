<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\TaxonomyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_terms',
  label: new TranslatableMarkup('Get Terms'),
  description: new TranslatableMarkup('Get taxonomy terms from a specific vocabulary.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'vocabulary' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup('The vocabulary machine name (e.g., "tags", "categories").'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum terms to return. Max 500.'),
      required: FALSE,
      default_value: 100,
    ),
    'hierarchical' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Hierarchical'),
      description: new TranslatableMarkup('If true, return terms in a nested hierarchy structure.'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'vocabulary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup('Machine name of the vocabulary queried.'),
    ),
    'vocabulary_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary Label'),
      description: new TranslatableMarkup('Human-readable name of the vocabulary.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Terms'),
      description: new TranslatableMarkup('Number of terms returned.'),
    ),
    'terms' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of terms with tid, name, description, weight, and parent. Use tid for entity reference fields.'),
    ),
  ],
)]
class GetTerms extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  protected TaxonomyService $taxonomy;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomy = $container->get('mcp_tools.taxonomy');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
