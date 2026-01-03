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
  id: 'mcp_structure_create_term',
  label: new TranslatableMarkup('Create Term'),
  description: new TranslatableMarkup('Create a new taxonomy term in a vocabulary.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'vocabulary' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('Vocabulary machine name'),
      required: TRUE,
    ),
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Term Name'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'parent' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Parent Term ID'),
      description: new TranslatableMarkup('For hierarchical terms'),
      required: FALSE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup(''),
      required: FALSE,
      default_value: 0,
    ),
  ],
  output_definitions: [
    'tid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Term ID'),
      description: new TranslatableMarkup(''),
    ),
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Term Name'),
      description: new TranslatableMarkup(''),
    ),
    'vocabulary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateTerm extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $vocabulary = $input['vocabulary'] ?? '';
    $name = $input['name'] ?? '';

    if (empty($vocabulary) || empty($name)) {
      return ['success' => FALSE, 'error' => 'Both vocabulary and name are required.'];
    }

    $options = [];
    if (isset($input['description'])) $options['description'] = $input['description'];
    if (isset($input['parent'])) $options['parent'] = $input['parent'];
    if (isset($input['weight'])) $options['weight'] = $input['weight'];

    return $this->taxonomyService->createTerm($vocabulary, $name, $options);
  }


}
