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
  id: 'mcp_pathauto_get_pattern',
  label: new TranslatableMarkup('Get Pathauto Pattern'),
  description: new TranslatableMarkup('Get details of a specific URL alias pattern.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('The machine name of the pattern to retrieve.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'selection_criteria' => new OutputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Applies to'),
      description: new TranslatableMarkup('The bundles and conditions the pattern applies to.'),
      required: FALSE,
    ),
    'id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Pattern ID'),
      description: new TranslatableMarkup('Machine name of the pattern.'),
    ),
    'label' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the pattern.'),
    ),
    'pattern' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL Pattern'),
      description: new TranslatableMarkup('Token pattern for URL aliases (e.g., "[node:title]" or "blog/[node:created:custom:Y]/[node:title]").'),
    ),
    'entity_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type this pattern applies to.'),
    ),
    'bundles' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Bundles'),
      description: new TranslatableMarkup('Content types/bundles this pattern applies to. Empty means all bundles.'),
    ),
    'weight' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Priority weight. Lower values are applied first.'),
    ),
    'status' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Enabled'),
      description: new TranslatableMarkup('Whether this pattern is active.'),
    ),
  ],
)]
class GetPattern extends McpToolsToolBase {

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

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID is required.'];
    }

    return $this->pathautoService->getPattern($id);
  }

}
