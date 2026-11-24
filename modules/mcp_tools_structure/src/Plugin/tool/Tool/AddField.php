<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\FieldService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_structure_add_field',
  label: new TranslatableMarkup('Add Field'),
  description: new TranslatableMarkup('Add a field to a content type, taxonomy vocabulary, or other entity bundle.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('node, taxonomy_term, user, etc.'),
      required: FALSE,
      default_value: 'node',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Content type or vocabulary ID'),
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
      description: new TranslatableMarkup('Plain text: string (one line), string_long (long). Formatted text: text (one line), text_long (long), text_with_summary (long, with a summary). Numbers: integer, decimal, float. Others: boolean, email, link, datetime (date and time), date (date only), timestamp, image, file, entity_reference, paragraphs (a field that holds paragraphs, on content or in a paragraph type; target_bundles names the paragraph types), and list_string or list_integer for a fixed set of options (see allowed_values). mcp_structure_list_field_types lists the ones this site has.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable field label shown in forms.'),
      required: TRUE,
    ),
    'required' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Required'),
      description: new TranslatableMarkup('True to make field required when creating/editing content.'),
      required: FALSE,
      default_value: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Help Text'),
      description: new TranslatableMarkup('Help text shown below the field in edit forms.'),
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
      description: new TranslatableMarkup('For entity_reference: node, taxonomy_term, user, media'),
      required: FALSE,
    ),
    'target_bundles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Reference Target Bundles'),
      description: new TranslatableMarkup('For entity_reference: the bundles it may point to, by machine name, e.g. ["article", "page"]. A bundle that does not exist is refused with the ones there are. Default: all.'),
      required: FALSE,
    ),
    'allowed_values' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Allowed Values'),
      description: new TranslatableMarkup('The options of a list_string or list_integer field: labels like ["Draft", "Final"], whole numbers like [1, 2, 3], or items like {"value": "d", "label": "Draft"}.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'field_name' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('Full field machine name (e.g., field_summary). Use this key in mcp_create_content fields parameter.'),
    ),
    'entity_type' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type the field was added to (e.g., node).'),
    ),
    'bundle' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Bundle the field was added to (e.g., article).'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class AddField extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  /**
   * The field service.
   *
   * @var \Drupal\mcp_tools_structure\Service\FieldService
   */
  protected FieldService $fieldService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';
    $fieldType = $input['field_type'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($bundle) || empty($fieldName) || empty($fieldType) || empty($label)) {
      return ['success' => FALSE, 'error' => 'bundle, field_name, field_type, and label are required.'];
    }

    $options = [];
    if (isset($input['required'])) {
      $options['required'] = $input['required'];
    }
    if (isset($input['description'])) {
      $options['description'] = $input['description'];
    }
    if (isset($input['cardinality'])) {
      $options['cardinality'] = $input['cardinality'];
    }
    if (isset($input['target_type'])) {
      $options['target_type'] = $input['target_type'];
    }
    if (isset($input['target_bundles'])) {
      $options['target_bundles'] = $input['target_bundles'];
    }
    if (isset($input['allowed_values'])) {
      $options['allowed_values'] = $input['allowed_values'];
    }

    return $this->fieldService->addField($entityType, $bundle, $fieldName, $fieldType, $label, $options);
  }

}
