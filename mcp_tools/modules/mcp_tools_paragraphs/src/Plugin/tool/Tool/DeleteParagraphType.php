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
  id: 'mcp_paragraphs_delete_type',
  label: new TranslatableMarkup('Delete Paragraph Type'),
  description: new TranslatableMarkup('Delete a paragraph type. Will fail if paragraphs exist unless force=true.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type ID'),
      description: new TranslatableMarkup('Machine name of the paragraph type to delete. Use ListParagraphTypes to see available types.'),
      required: TRUE,
    ),
    'force' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Force Delete'),
      description: new TranslatableMarkup('Delete even if paragraphs exist (dangerous!)'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Paragraph Type ID'),
      description: new TranslatableMarkup('Machine name of the deleted paragraph type. Use CreateParagraphType to recreate if needed.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the deletion. WARNING: This operation cannot be undone.'),
    ),
    'deleted_paragraphs' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Paragraphs Count'),
      description: new TranslatableMarkup('Number of paragraph entities deleted when force=true. Zero if no paragraphs existed.'),
    ),
  ],
)]
class DeleteParagraphType extends McpToolsToolBase {

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

    return $this->paragraphsService->deleteParagraphType($id, $input['force'] ?? FALSE);
  }


}
