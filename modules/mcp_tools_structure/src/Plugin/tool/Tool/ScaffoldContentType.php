<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\mcp_tools_structure\Service\FieldService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Scaffold a complete content type with fields in one operation.
 */
#[Tool(
  id: 'mcp_structure_scaffold_content_type',
  label: new TranslatableMarkup('Scaffold Content Type'),
  description: new TranslatableMarkup('Create a content type with multiple fields in a single operation. Reduces round-trips compared to calling CreateContentType + AddField multiple times. Includes common defaults like title, body, and specified custom fields.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Machine name for the content type (e.g., "blog_post", "product"). Lowercase letters, numbers, underscores only. Max 32 characters.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Blog Post", "Product").'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Description of the content type shown to content editors.'),
      required: FALSE,
    ),
    'fields' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Array of field definitions. Each field: {"name": "field_name", "type": "string|text_long|integer|boolean|entity_reference|image|file|link|email|datetime|list_string", "label": "Field Label", "required": false, "cardinality": 1, "description": "Help text"}. For entity_reference: add "target_type": "node|taxonomy_term|user|media". For list_string: add "allowed_values": ["option1", "option2"].'),
      required: FALSE,
    ),
    'include_body' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Include Body Field'),
      description: new TranslatableMarkup('Add the standard body field with summary. Defaults to TRUE.'),
      required: FALSE,
      default_value: TRUE,
    ),
  ],
  output_definitions: [
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Machine name of the created content type.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the content type.'),
    ),
    'fields_created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields Created'),
      description: new TranslatableMarkup('List of field machine names that were created.'),
    ),
    'fields_failed' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields Failed'),
      description: new TranslatableMarkup('List of fields that failed to create with error messages.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage the content type in admin UI.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of what was created.'),
    ),
  ],
)]
class ScaffoldContentType extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected ContentTypeService $contentTypeService;
  protected FieldService $fieldService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $description = $input['description'] ?? '';
    $fields = $input['fields'] ?? [];
    $includeBody = $input['include_body'] ?? TRUE;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Machine name (id) is required.'];
    }

    if (empty($label)) {
      return ['success' => FALSE, 'error' => 'Label is required.'];
    }

    // Step 1: Create the content type.
    $typeResult = $this->contentTypeService->createContentType($id, $label, $description);
    if (!($typeResult['success'] ?? FALSE)) {
      return $typeResult;
    }

    $fieldsCreated = [];
    $fieldsFailed = [];

    // Step 2: Add body field if requested.
    if ($includeBody) {
      $bodyResult = $this->fieldService->addField('node', $id, 'body', 'text_with_summary', 'Body', FALSE, 1);
      if ($bodyResult['success'] ?? FALSE) {
        $fieldsCreated[] = 'body';
      }
      else {
        $fieldsFailed[] = ['field' => 'body', 'error' => $bodyResult['error'] ?? 'Unknown error'];
      }
    }

    // Step 3: Add custom fields.
    foreach ($fields as $field) {
      $fieldName = $field['name'] ?? '';
      $fieldType = $field['type'] ?? 'string';
      $fieldLabel = $field['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
      $required = $field['required'] ?? FALSE;
      $cardinality = $field['cardinality'] ?? 1;

      if (empty($fieldName)) {
        $fieldsFailed[] = ['field' => '(unnamed)', 'error' => 'Field name is required'];
        continue;
      }

      // Build settings based on field type.
      $settings = [];
      if (isset($field['target_type'])) {
        $settings['target_type'] = $field['target_type'];
      }
      if (isset($field['allowed_values'])) {
        $settings['allowed_values'] = $field['allowed_values'];
      }
      if (isset($field['description'])) {
        $settings['description'] = $field['description'];
      }

      $fieldResult = $this->fieldService->addField(
        'node',
        $id,
        $fieldName,
        $fieldType,
        $fieldLabel,
        $required,
        $cardinality,
        $settings
      );

      if ($fieldResult['success'] ?? FALSE) {
        $fieldsCreated[] = $fieldName;
      }
      else {
        $fieldsFailed[] = ['field' => $fieldName, 'error' => $fieldResult['error'] ?? 'Unknown error'];
      }
    }

    $totalFields = count($fieldsCreated);
    $failedCount = count($fieldsFailed);

    $message = "Created content type '$label' ($id) with $totalFields fields.";
    if ($failedCount > 0) {
      $message .= " $failedCount fields failed to create.";
    }

    return [
      'success' => TRUE,
      'data' => [
        'content_type' => $id,
        'label' => $label,
        'fields_created' => $fieldsCreated,
        'fields_failed' => $fieldsFailed,
        'admin_path' => "/admin/structure/types/manage/$id/fields",
        'message' => $message,
      ],
    ];
  }

}
