<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\tool\Tool;

use Drupal\mcp_tools_metatag\Service\MetatagService;
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
  id: 'mcp_metatag_get_entity',
  label: new TranslatableMarkup('Get Entity Metatags'),
  description: new TranslatableMarkup('Get metatags for a specific entity (node, term, user, etc.).'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type (e.g., "node", "taxonomy_term", "user").'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup(''),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup(''),
    ),
    'entity_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Label'),
      description: new TranslatableMarkup(''),
    ),
    'stored_tags' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Stored Metatags'),
      description: new TranslatableMarkup(''),
    ),
    'computed_tags' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Computed Metatags'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetEntityMetatags extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'metatag';


  protected MetatagService $metatagService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->metatagService = $container->get('mcp_tools_metatag.metatag');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? '';
    $entityId = $input['entity_id'] ?? 0;

    if (empty($entityType) || empty($entityId)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and entity_id are required.'];
    }

    return $this->metatagService->getEntityMetatags($entityType, (int) $entityId);
  }

  

  

}
