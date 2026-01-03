<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting fields from paragraph types.
 *
 * @Tool(
 *   id = "mcp_paragraphs_delete_field",
 *   label = @Translation("Delete Paragraph Field"),
 *   description = @Translation("Remove a field from a paragraph type."),
 *   category = "paragraphs",
 * )
 */
class DeleteParagraphField extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  public function execute(array $input = []): array {
    $bundle = $input['bundle'] ?? '';
    $fieldName = $input['field_name'] ?? '';

    if (empty($bundle) || empty($fieldName)) {
      return ['success' => FALSE, 'error' => 'Both bundle and field_name are required.'];
    }

    return $this->paragraphsService->deleteField($bundle, $fieldName);
  }

  public function getInputDefinition(): array {
    return [
      'bundle' => ['type' => 'string', 'label' => 'Paragraph Type', 'required' => TRUE, 'description' => 'Paragraph type machine name'],
      'field_name' => ['type' => 'string', 'label' => 'Field Name', 'required' => TRUE, 'description' => 'Field machine name (with or without field_ prefix)'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'field_name' => ['type' => 'string', 'label' => 'Deleted Field Name'],
      'bundle' => ['type' => 'string', 'label' => 'Paragraph Type'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
