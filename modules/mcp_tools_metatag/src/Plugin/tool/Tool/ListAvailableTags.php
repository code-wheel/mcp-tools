<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_metatag\Plugin\tool\Tool;

use Drupal\mcp_tools_metatag\Service\MetatagService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_metatag_list_tags',
  label: new TranslatableMarkup('List Available Metatag Tags'),
  description: new TranslatableMarkup('List the metatag tags with their descriptions and groups: all, or the tags of one group (group, for example "open_graph").'),
  operation: ToolOperation::Read,
  input_definitions: [
    'group' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Group'),
      description: new TranslatableMarkup('Only the tags of this group: a group_id, for example "open_graph" or "basic".'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Tags'),
      description: new TranslatableMarkup('Total number of metatag tags available.'),
    ),
    'by_group' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Tags Grouped'),
      description: new TranslatableMarkup('The groups (group_id, group_label) with the ids of their tags, in weight order. Pass a group_id as group for the tags of that group only.'),
    ),
    'all_tags' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('All Tags'),
      description: new TranslatableMarkup('Each tag once: id, label, description when it has one, group, type, and secure or multiple when true. Use the id as a key of tags in mcp_metatag_set_entity.'),
    ),
    'matching' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Matching'),
      description: new TranslatableMarkup('With group: how many tags it has.'),
    ),
  ],
)]
class ListAvailableTags extends McpToolsToolBase {

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
    return $this->metatagService->listAvailableTags(isset($input['group']) ? (string) $input['group'] : NULL);
  }

}
