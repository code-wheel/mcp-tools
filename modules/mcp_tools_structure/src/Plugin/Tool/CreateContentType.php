<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating content types.
 *
 * @Tool(
 *   id = "mcp_structure_create_content_type",
 *   label = @Translation("Create Content Type"),
 *   description = @Translation("Create a new content type with optional body field."),
 *   category = "structure",
 * )
 */
class CreateContentType extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ContentTypeService $contentTypeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->contentTypeService->createContentType($id, $label, [
      'description' => $input['description'] ?? '',
      'create_body' => $input['create_body'] ?? TRUE,
    ]);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE, 'description' => 'Lowercase, underscores (e.g., "blog_post")'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE, 'description' => 'Human-readable name (e.g., "Blog Post")'],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
      'create_body' => ['type' => 'boolean', 'label' => 'Create Body Field', 'required' => FALSE, 'default' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Content Type ID'],
      'label' => ['type' => 'string', 'label' => 'Content Type Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
