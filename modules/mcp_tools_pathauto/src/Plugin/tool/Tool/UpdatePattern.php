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
  id: 'mcp_pathauto_update',
  label: new TranslatableMarkup('Update Pathauto Pattern'),
  description: new TranslatableMarkup('Update an existing URL alias pattern. This is a write operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('The machine name of the pattern to update.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('New human-readable name for the pattern.'),
      required: FALSE,
    ),
    'pattern' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL Pattern'),
      description: new TranslatableMarkup('New URL alias pattern using tokens.'),
      required: FALSE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Pattern weight (lower weights are processed first).'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Enabled'),
      description: new TranslatableMarkup('Whether the pattern is enabled (true/false).'),
      required: FALSE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Bundle to restrict the pattern to (null to remove restriction).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('Machine name of the updated pattern.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Current label of the pattern.'),
    ),
    'pattern' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL Pattern'),
      description: new TranslatableMarkup('Current URL alias pattern.'),
    ),
    'updated_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated Fields'),
      description: new TranslatableMarkup('List of fields that were changed.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class UpdatePattern extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'pathauto';


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
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID is required.'];
    }

    // Build values array from provided inputs.
    $values = [];

    if (isset($input['label']) && $input['label'] !== '') {
      $values['label'] = $input['label'];
    }

    if (isset($input['pattern']) && $input['pattern'] !== '') {
      $values['pattern'] = $input['pattern'];
    }

    if (isset($input['weight'])) {
      $values['weight'] = (int) $input['weight'];
    }

    if (isset($input['status'])) {
      $values['status'] = (bool) $input['status'];
    }

    if (array_key_exists('bundle', $input)) {
      $values['bundle'] = $input['bundle'];
    }

    if (empty($values)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required (label, pattern, weight, status, or bundle).'];
    }

    return $this->pathautoService->updatePattern($id, $values);
  }

  

  

}
