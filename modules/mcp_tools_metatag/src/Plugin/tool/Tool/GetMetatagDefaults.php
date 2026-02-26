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
  id: 'mcp_metatag_get_defaults',
  label: new TranslatableMarkup('Get Metatag Defaults'),
  description: new TranslatableMarkup('Get default metatag configuration, optionally filtered by entity type.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Optional entity type to get defaults for (e.g., "node", "taxonomy_term", "article").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Defaults'),
      description: new TranslatableMarkup('Total number of default configurations returned.'),
    ),
    'defaults' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Metatag Defaults'),
      description: new TranslatableMarkup('Array of default configurations with id, label, entity_type, and tags. Tags can use tokens like [node:title].'),
    ),
  ],
)]
class GetMetatagDefaults extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'metatag';


  /**
   * The metatag service.
   *
   * @var \Drupal\mcp_tools_metatag\Service\MetatagService
   */
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
    $type = $input['type'] ?? NULL;
    return $this->metatagService->getMetatagDefaults($type);
  }

}
