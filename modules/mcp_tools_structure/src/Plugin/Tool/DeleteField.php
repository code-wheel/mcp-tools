<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting fields.
 *
 * @Tool(
 *   id = "mcp_structure_delete_field",
 *   label = @Translation("Delete Field"),
 *   description = @Translation("Delete a field from an entity bundle. Data will be lost!"),
 *   category = "structure",
 * )
 */
class DeleteField extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($bundle) || empty($fieldName)) {
      return ['success' => FALSE, 'error' => 'bundle and field_name are required.'];
    }

    return $this->fieldService->deleteField($entityType, $bundle, $fieldName);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'required' => FALSE, 'default' => 'node'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle', 'required' => TRUE],
      'field_name' => ['type' => 'string', 'label' => 'Field Name', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'field_name' => ['type' => 'string', 'label' => 'Deleted Field Name'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
