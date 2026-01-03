<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for adding fields to paragraph types.
 *
 * @Tool(
 *   id = "mcp_paragraphs_add_field",
 *   label = @Translation("Add Paragraph Field"),
 *   description = @Translation("Add a field to a paragraph type."),
 *   category = "paragraphs",
 * )
 */
class AddParagraphField extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'bundle' => ['type' => 'string', 'label' => 'Paragraph Type', 'required' => TRUE, 'description' => 'Paragraph type machine name'],
      'field_name' => ['type' => 'string', 'label' => 'Field Name', 'required' => TRUE, 'description' => 'Machine name (field_ prefix auto-added)'],
      'field_type' => ['type' => 'string', 'label' => 'Field Type', 'required' => TRUE, 'description' => 'string, text_long, integer, boolean, entity_reference, image, etc.'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => FALSE, 'description' => 'Human-readable label (auto-generated if not provided)'],
      'required' => ['type' => 'boolean', 'label' => 'Required', 'required' => FALSE, 'default' => FALSE],
      'description' => ['type' => 'string', 'label' => 'Help Text', 'required' => FALSE],
      'cardinality' => ['type' => 'integer', 'label' => 'Cardinality', 'required' => FALSE, 'default' => 1, 'description' => '1 for single, -1 for unlimited'],
      'target_type' => ['type' => 'string', 'label' => 'Reference Target Type', 'required' => FALSE, 'description' => 'For entity_reference: node, taxonomy_term, user, media, paragraph'],
      'target_bundles' => ['type' => 'list', 'label' => 'Reference Target Bundles', 'required' => FALSE, 'description' => 'Limit references to specific bundles'],
      'allowed_values' => ['type' => 'list', 'label' => 'Allowed Values', 'required' => FALSE, 'description' => 'For list fields'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'field_name' => ['type' => 'string', 'label' => 'Field Name'],
      'bundle' => ['type' => 'string', 'label' => 'Paragraph Type'],
      'field_type' => ['type' => 'string', 'label' => 'Field Type'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
