<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\TaxonomyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin to list all vocabularies.
 */
#[Tool(
  id: 'mcp_structure_list_vocabularies',
  label: new TranslatableMarkup('List Vocabularies'),
  description: new TranslatableMarkup('List all taxonomy vocabularies with term counts. Use this to discover available classification systems before creating terms or referencing taxonomies.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'vocabularies' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Vocabularies'),
      description: new TranslatableMarkup('Array of vocabularies with id, label, description, and term_count. Use id with GetVocabulary to see terms or CreateTerm to add terms.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Vocabularies'),
      description: new TranslatableMarkup('Total number of vocabularies in the system.'),
    ),
  ],
)]
class ListVocabularies extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->taxonomyService->listVocabularies();
  }

}
