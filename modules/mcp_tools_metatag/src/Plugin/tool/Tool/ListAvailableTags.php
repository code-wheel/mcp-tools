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
  id: 'mcp_metatag_list_tags',
  label: new TranslatableMarkup('List Available Metatag Tags'),
  description: new TranslatableMarkup('List all available metatag tags with their descriptions and group assignments.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Tags'),
      description: new TranslatableMarkup(''),
    ),
    'by_group' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Tags Grouped'),
      description: new TranslatableMarkup(''),
    ),
    'all_tags' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('All Tags'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListAvailableTags extends McpToolsToolBase {

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
    return $this->metatagService->listAvailableTags();
  }

  

  

}
