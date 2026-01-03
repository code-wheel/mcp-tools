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
  id: 'mcp_metatag_list_groups',
  label: new TranslatableMarkup('List Metatag Groups'),
  description: new TranslatableMarkup('List all available metatag groups (basic, open_graph, twitter_cards, etc.).'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Groups'),
      description: new TranslatableMarkup(''),
    ),
    'groups' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Metatag Groups'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListMetatagGroups extends McpToolsToolBase {

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
    return $this->metatagService->listMetatagGroups();
  }

  

  

}
