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
  id: 'mcp_structure_create_vocabulary',
  label: new TranslatableMarkup('Create Vocabulary'),
  description: new TranslatableMarkup('Create a new taxonomy vocabulary.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Lowercase, underscores (e.g., "tags")'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Tags")'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary Label'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateVocabulary extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->taxonomyService->createVocabulary($id, $label, $input['description'] ?? '');
  }


}
