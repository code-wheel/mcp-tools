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
  id: 'mcp_pathauto_create',
  label: new TranslatableMarkup('Create Pathauto Pattern'),
  description: new TranslatableMarkup('Create a new URL alias pattern. This is a write operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('Machine name for the pattern (lowercase, underscores allowed).'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name for the pattern.'),
      required: TRUE,
    ),
    'pattern' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL Pattern'),
      description: new TranslatableMarkup('The URL alias pattern using tokens (e.g., "blog/[node:title]", "[term:vocabulary]/[term:name]").'),
      required: TRUE,
    ),
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type this pattern applies to (e.g., "node", "taxonomy_term", "user").'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Optional bundle (content type, vocabulary) to restrict the pattern to.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('Machine name of the created pattern.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the pattern.'),
    ),
    'pattern' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL Pattern'),
      description: new TranslatableMarkup('The token pattern for URL aliases.'),
    ),
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type this pattern applies to.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Bundle restriction, if any.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error details.'),
    ),
  ],
)]
class CreatePattern extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'pathauto';


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
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $pattern = $input['pattern'] ?? '';
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? NULL;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID (machine name) is required.'];
    }

    if (empty($label)) {
      return ['success' => FALSE, 'error' => 'Pattern label is required.'];
    }

    if (empty($pattern)) {
      return ['success' => FALSE, 'error' => 'URL pattern is required.'];
    }

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    // Validate machine name format.
    if (!preg_match('/^[a-z0-9_]+$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Pattern ID must contain only lowercase letters, numbers, and underscores.',
      ];
    }

    return $this->pathautoService->createPattern($id, $label, $pattern, $entityType, $bundle);
  }

}
