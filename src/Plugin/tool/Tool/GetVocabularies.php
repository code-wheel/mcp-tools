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
  id: 'mcp_tools_get_vocabularies',
  label: new TranslatableMarkup('Get Vocabularies'),
  description: new TranslatableMarkup('Get all taxonomy vocabularies with term counts.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_vocabularies' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Vocabularies'),
      description: new TranslatableMarkup('Number of taxonomy vocabularies on the site.'),
    ),
    'vocabularies' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Vocabularies'),
      description: new TranslatableMarkup('Array of vocabularies with vid (machine name), label, description, and term_count. Use vid with GetTerms or when creating entity references.'),
    ),
  ],
)]
class GetVocabularies extends McpToolsToolBase {

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
    return [
      'success' => TRUE,
      'data' => $this->taxonomy->getVocabularies(),
    ];
  }

  

  

}
