<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\TaxonomyManagementService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_structure_create_terms',
  label: new TranslatableMarkup('Create Multiple Terms'),
  description: new TranslatableMarkup('Create multiple taxonomy terms in a vocabulary at once.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'vocabulary' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('Vocabulary machine name. Use mcp_tools_get_vocabularies to see available vocabularies.'),
      required: TRUE,
    ),
    'terms' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of strings (term names) or objects with {name, description, parent, weight}. parent is a term id or a term name, also of a term made earlier in this list, so a small tree goes in one call. More efficient than multiple mcp_structure_create_term calls.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'vocabulary' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('Vocabulary terms were added to.'),
    ),
    'created_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Terms Created'),
      description: new TranslatableMarkup('Number of terms successfully created.'),
    ),
    'error_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Number of terms that failed to create.'),
    ),
    'created' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Terms'),
      description: new TranslatableMarkup('Array of created terms with tid and name.'),
    ),
    'errors' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of error messages for failed terms.'),
    ),
  ],
)]
class CreateTerms extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';
  protected const MCP_WRITE_KIND = 'content';


  /**
   * The taxonomy service.
   *
   * @var \Drupal\mcp_tools_structure\Service\TaxonomyManagementService
   */
  protected TaxonomyManagementService $taxonomyService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $vocabulary = $input['vocabulary'] ?? '';
    $terms = $input['terms'] ?? [];

    if (empty($vocabulary)) {
      return ['success' => FALSE, 'error' => 'Vocabulary is required.'];
    }

    if (empty($terms)) {
      return ['success' => FALSE, 'error' => 'At least one term is required.'];
    }

    return $this->taxonomyService->createTerms($vocabulary, $terms);
  }

}
