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
  id: 'mcp_paragraphs_delete_field',
  label: new TranslatableMarkup('Delete Paragraph Field'),
  description: new TranslatableMarkup('Remove a field from a paragraph type.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type'),
      description: new TranslatableMarkup('Paragraph type machine name'),
      required: TRUE,
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('Field machine name (with or without field_ prefix)'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Field Name'),
      description: new TranslatableMarkup('Machine name of the deleted field. WARNING: All data in this field is permanently lost.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type'),
      description: new TranslatableMarkup('Paragraph type the field was removed from.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the field deletion.'),
    ),
  ],
)]
class DeleteParagraphField extends McpToolsToolBase {

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
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';

    if (empty($bundle) || empty($fieldName)) {
      return ['success' => FALSE, 'error' => 'Both bundle and field_name are required.'];
    }

    return $this->paragraphsService->deleteField($bundle, $fieldName);
  }

}
