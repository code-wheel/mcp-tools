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
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'terms' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of term names or objects with name/description/parent'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'vocabulary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup(''),
    ),
    'created_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Terms Created'),
      description: new TranslatableMarkup(''),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup(''),
    ),
    'created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Terms'),
      description: new TranslatableMarkup(''),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateTerms extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';
  protected const MCP_WRITE_KIND = 'content';


  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

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
