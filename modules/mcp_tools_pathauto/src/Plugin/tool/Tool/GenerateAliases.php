<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\tool\Tool;

use Drupal\mcp_tools_pathauto\Service\PathautoService;
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
  id: 'mcp_pathauto_generate',
  label: new TranslatableMarkup('Generate URL Aliases'),
  description: new TranslatableMarkup('Bulk generate URL aliases for entities using Pathauto patterns. This is a write operation.'),
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
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup(''),
    ),
    'processed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entities Processed'),
      description: new TranslatableMarkup(''),
    ),
    'created' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Aliases Created'),
      description: new TranslatableMarkup(''),
    ),
    'updated' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Aliases Updated'),
      description: new TranslatableMarkup(''),
    ),
    'skipped' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Skipped'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GenerateAliases extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'pathauto';
  protected const MCP_WRITE_KIND = 'content';


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
    $bundle = $input['bundle'] ?? NULL;
    $update = $input['update'] ?? FALSE;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    return $this->pathautoService->generateAliases($entityType, $bundle, (bool) $update);
  }

  

  

}
