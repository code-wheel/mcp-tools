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
  id: 'mcp_paragraphs_add_field',
  label: new TranslatableMarkup('Add Paragraph Field'),
  description: new TranslatableMarkup('Add a field to a paragraph type.'),
  operation: ToolOperation::Write,
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
      description: new TranslatableMarkup('Machine name (field_ prefix auto-added)'),
      required: TRUE,
    ),
    'field_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Type'),
      description: new TranslatableMarkup('string, text_long, integer, boolean, entity_reference, image, etc.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable label (auto-generated if not provided)'),
      required: FALSE,
    ),
    'required' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Required'),
      description: new TranslatableMarkup('Whether this field is required when creating paragraphs. Default: FALSE.'),
      required: FALSE,
      default_value: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Help Text'),
      description: new TranslatableMarkup('Help text displayed below the field in the edit form.'),
      required: FALSE,
    ),
    'cardinality' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Cardinality'),
      description: new TranslatableMarkup('1 for single, -1 for unlimited'),
      required: FALSE,
      default_value: 1,
    ),
    'target_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Reference Target Type'),
      description: new TranslatableMarkup('For entity_reference: node, taxonomy_term, user, media, paragraph'),
      required: FALSE,
    ),
    'target_bundles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Reference Target Bundles'),
      description: new TranslatableMarkup('Limit references to specific bundles'),
      required: FALSE,
    ),
    'allowed_values' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Allowed Values'),
      description: new TranslatableMarkup('For list fields'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('Full machine name of the created field (includes field_ prefix). Use with DeleteParagraphField to remove.'),
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph Type'),
      description: new TranslatableMarkup('Paragraph type the field was added to. Use GetParagraphType to view all fields.'),
    ),
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Type'),
      description: new TranslatableMarkup('Type of field created (e.g., string, text_long, entity_reference).'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable label assigned to the field.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the field creation.'),
    ),
  ],
)]
class AddParagraphField extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'paragraphs';


  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';
    $fieldType = $input['field_type'] ?? '';

    if (empty($bundle) || empty($fieldName) || empty($fieldType)) {
      return ['success' => FALSE, 'error' => 'bundle, field_name, and field_type are required.'];
    }

    $settings = [];
    if (isset($input['label'])) {
      $settings['label'] = $input['label'];
    }
    if (isset($input['required'])) {
      $settings['required'] = $input['required'];
    }
    if (isset($input['description'])) {
      $settings['description'] = $input['description'];
    }
    if (isset($input['cardinality'])) {
      $settings['cardinality'] = $input['cardinality'];
    }
    if (isset($input['target_type'])) {
      $settings['target_type'] = $input['target_type'];
    }
    if (isset($input['target_bundles'])) {
      $settings['target_bundles'] = $input['target_bundles'];
    }
    if (isset($input['allowed_values'])) {
      $settings['allowed_values'] = $input['allowed_values'];
    }

    return $this->paragraphsService->addField($bundle, $fieldName, $fieldType, $settings);
  }


}
