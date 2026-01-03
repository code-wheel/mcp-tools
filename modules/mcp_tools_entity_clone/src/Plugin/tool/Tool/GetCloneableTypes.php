<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\tool\Tool;

use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
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
  id: 'mcp_entity_clone_types',
  label: new TranslatableMarkup('Get Cloneable Entity Types'),
  description: new TranslatableMarkup('List all entity types that support cloning with their bundles.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'types' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Cloneable Entity Types'),
      description: new TranslatableMarkup(''),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Types'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetCloneableTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'entity_clone';


  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->entityCloneService->getCloneableTypes();
  }


}
