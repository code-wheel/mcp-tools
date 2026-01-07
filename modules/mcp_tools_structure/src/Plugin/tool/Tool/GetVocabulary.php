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
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin to get vocabulary details with terms.
 */
#[Tool(
  id: 'mcp_structure_get_vocabulary',
  label: new TranslatableMarkup('Get Vocabulary'),
  description: new TranslatableMarkup('Get vocabulary details including terms with hierarchy information. Use this to see available taxonomy terms before referencing them in content.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup('Machine name of the vocabulary (e.g., "tags", "categories"). Use ListVocabularies to see available vocabularies.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Term Limit'),
      description: new TranslatableMarkup('Maximum number of terms to return (default: 100, 0 for all).'),
      required: FALSE,
      default_value: 100,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup('Machine name of the vocabulary.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the vocabulary.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Description of the vocabulary purpose.'),
    ),
    'terms' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of terms with tid, name, description, weight, and parent tid (0 for root). Use tid when creating entity references to taxonomy terms.'),
    ),
    'terms_returned' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Terms Returned'),
      description: new TranslatableMarkup('Number of terms in this response (may be less than total_terms if limit applied).'),
    ),
    'total_terms' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Terms'),
      description: new TranslatableMarkup('Total number of terms in the vocabulary.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage this vocabulary in admin UI.'),
    ),
  ],
)]
class GetVocabulary extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $limit = (int) ($input['limit'] ?? 100);

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Vocabulary ID is required.'];
    }

    return $this->taxonomyService->getVocabulary($id, $limit);
  }

}
