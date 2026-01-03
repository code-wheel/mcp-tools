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
  id: 'mcp_paragraphs_create_type',
  label: new TranslatableMarkup('Create Paragraph Type'),
  description: new TranslatableMarkup('Create a new paragraph type.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Lowercase, underscores (e.g., "text_block")'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Text Block")'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup(''),
      required: FALSE,
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
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateParagraphType extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'paragraphs';


  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->paragraphsService->createParagraphType(
      $id,
      $label,
      $input['description'] ?? ''
    );
  }


}
