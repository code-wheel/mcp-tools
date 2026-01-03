<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for adding fields to entity bundles.
 *
 * @Tool(
 *   id = "mcp_structure_add_field",
 *   label = @Translation("Add Field"),
 *   description = @Translation("Add a field to a content type, taxonomy vocabulary, or other entity bundle."),
 *   category = "structure",
 * )
 */
class AddField extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected FieldService $fieldService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? 'node';
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';
    $fieldType = $input['field_type'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($bundle) || empty($fieldName) || empty($fieldType) || empty($label)) {
      return ['success' => FALSE, 'error' => 'bundle, field_name, field_type, and label are required.'];
    }

    $options = [];
    if (isset($input['required'])) $options['required'] = $input['required'];
    if (isset($input['description'])) $options['description'] = $input['description'];
    if (isset($input['cardinality'])) $options['cardinality'] = $input['cardinality'];
    if (isset($input['target_type'])) $options['target_type'] = $input['target_type'];
    if (isset($input['target_bundles'])) $options['target_bundles'] = $input['target_bundles'];
    if (isset($input['allowed_values'])) $options['allowed_values'] = $input['allowed_values'];

    return $this->fieldService->addField($entityType, $bundle, $fieldName, $fieldType, $label, $options);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node', 'description' => 'node, taxonomy_term, user, etc.'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle', 'required' => TRUE, 'description' => 'Content type or vocabulary ID'],
      'field_name' => ['type' => 'string', 'label' => 'Field Name', 'required' => TRUE, 'description' => 'Machine name (field_ prefix auto-added)'],
      'field_type' => ['type' => 'string', 'label' => 'Field Type', 'required' => TRUE, 'description' => 'string, text_long, integer, boolean, entity_reference, image, etc.'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE],
      'required' => ['type' => 'boolean', 'label' => 'Required', 'required' => FALSE, 'default' => FALSE],
      'description' => ['type' => 'string', 'label' => 'Help Text', 'required' => FALSE],
      'cardinality' => ['type' => 'integer', 'label' => 'Cardinality', 'required' => FALSE, 'default' => 1, 'description' => '1 for single, -1 for unlimited'],
      'target_type' => ['type' => 'string', 'label' => 'Reference Target Type', 'required' => FALSE, 'description' => 'For entity_reference: node, taxonomy_term, user, media'],
      'target_bundles' => ['type' => 'list', 'label' => 'Reference Target Bundles', 'required' => FALSE, 'description' => 'Limit references to specific bundles'],
      'allowed_values' => ['type' => 'list', 'label' => 'Allowed Values', 'required' => FALSE, 'description' => 'For list fields'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'field_name' => ['type' => 'string', 'label' => 'Field Name'],
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
