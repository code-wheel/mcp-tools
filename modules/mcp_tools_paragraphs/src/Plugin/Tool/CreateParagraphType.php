<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating paragraph types.
 *
 * @Tool(
 *   id = "mcp_paragraphs_create_type",
 *   label = @Translation("Create Paragraph Type"),
 *   description = @Translation("Create a new paragraph type."),
 *   category = "paragraphs",
 * )
 */
class CreateParagraphType extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE, 'description' => 'Lowercase, underscores (e.g., "text_block")'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE, 'description' => 'Human-readable name (e.g., "Text Block")'],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Paragraph Type ID'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
    ];
  }

}
