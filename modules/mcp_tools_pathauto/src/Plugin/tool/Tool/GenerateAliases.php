<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\tool\Tool;

use Drupal\mcp_tools_pathauto\Service\PathautoService;
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
  id: 'mcp_pathauto_generate',
  label: new TranslatableMarkup('Generate URL Aliases'),
  description: new TranslatableMarkup('Bulk generate URL aliases for entities using Pathauto patterns.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type to generate aliases for (e.g., "node", "taxonomy_term", "user").'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Optional bundle (content type, vocabulary) to limit generation.'),
      required: FALSE,
    ),
    'update' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Update Existing'),
      description: new TranslatableMarkup('If true, update existing aliases. If false (default), only create missing aliases.'),
      required: FALSE,
      default_value: FALSE,
    ),
    'after' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('After'),
      description: new TranslatableMarkup('Start after this entity id: the next_after of the last call. A call does at most 50; call again until done is true.'),
      required: FALSE,
      default_value: 0,
    ),
  ],
  output_definitions: [
    'entity_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type aliases were generated for.'),
    ),
    'bundle' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Bundle filtered on, or NULL if all bundles were processed.'),
    ),
    'processed' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entities Processed'),
      description: new TranslatableMarkup('Total number of entities examined during generation.'),
    ),
    'created' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Aliases Created'),
      description: new TranslatableMarkup('Number of new URL aliases created for entities that had none.'),
    ),
    'updated' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Aliases Updated'),
      description: new TranslatableMarkup('Number of existing aliases regenerated (only when update=true).'),
    ),
    'skipped' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Skipped'),
      description: new TranslatableMarkup('Number of entities skipped (already have aliases when update=false, or no pattern configured).'),
    ),
    'next_after' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Next after'),
      description: new TranslatableMarkup('Give this as after to the next call.'),
    ),
    'remaining' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Remaining'),
      description: new TranslatableMarkup('Entities still to do after this call.'),
    ),
    'done' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Done'),
      description: new TranslatableMarkup('Whether every entity has been processed.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable summary of the alias generation results.'),
    ),
  ],
)]
class GenerateAliases extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'pathauto';
  protected const MCP_WRITE_KIND = 'content';


  /**
   * The pathauto service.
   *
   * @var \Drupal\mcp_tools_pathauto\Service\PathautoService
   */
  protected PathautoService $pathautoService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pathautoService = $container->get('mcp_tools_pathauto.pathauto');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $bundle = ($input['bundle'] ?? NULL) ?: NULL;
    $update = $input['update'] ?? FALSE;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    return $this->pathautoService->generateAliases($entityType, $bundle, (bool) $update, (int) ($input['after'] ?? 0));
  }

}
