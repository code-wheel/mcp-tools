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
  id: 'mcp_pathauto_list_patterns',
  label: new TranslatableMarkup('List Pathauto Patterns'),
  description: new TranslatableMarkup('List all URL alias patterns configured in Pathauto.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Optional entity type to filter patterns (e.g., "node", "taxonomy_term", "user").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Patterns'),
      description: new TranslatableMarkup(''),
    ),
    'patterns' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Pattern List'),
      description: new TranslatableMarkup(''),
    ),
    'filter' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Applied Filter'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListPatterns extends McpToolsToolBase {

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
    $entityType = $input['entity_type'] ?? NULL;

    return $this->pathautoService->listPatterns($entityType);
  }

  

  

}
