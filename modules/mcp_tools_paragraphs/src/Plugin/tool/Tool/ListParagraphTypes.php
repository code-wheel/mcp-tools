<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\tool\Tool;

use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_paragraphs_list_types',
  label: new TranslatableMarkup('List Paragraph Types'),
  description: new TranslatableMarkup('List all paragraph types with their fields.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'types' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Paragraph Types'),
      description: new TranslatableMarkup('Array of paragraph type objects with id, label, description, and field count. Use id with GetParagraphType for full details.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Types'),
      description: new TranslatableMarkup('Total number of paragraph types in the system.'),
    ),
  ],
)]
class ListParagraphTypes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'paragraphs';


  /**
   * The paragraphs service.
   *
   * @var \Drupal\mcp_tools_paragraphs\Service\ParagraphsService
   */
  protected ParagraphsService $paragraphsService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->paragraphsService->listParagraphTypes();
  }

}
