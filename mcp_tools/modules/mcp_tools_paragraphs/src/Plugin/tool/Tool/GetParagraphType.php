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
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_paragraphs_get_type',
  label: new TranslatableMarkup('Get Paragraph Type'),
  description: new TranslatableMarkup('Get details of a specific paragraph type including its fields.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type ID'),
      description: new TranslatableMarkup('Machine name of the paragraph type'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type ID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup(''),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup(''),
    ),
    'fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup(''),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetParagraphType extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'paragraphs';


  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Paragraph type id is required.'];
    }

    return $this->paragraphsService->getParagraphType($id);
  }


}
