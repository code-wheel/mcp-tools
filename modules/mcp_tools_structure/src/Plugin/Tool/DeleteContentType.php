<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting content types.
 *
 * @Tool(
 *   id = "mcp_structure_delete_content_type",
 *   label = @Translation("Delete Content Type"),
 *   description = @Translation("Delete a content type. Will fail if content exists unless force=true."),
 *   category = "structure",
 * )
 */
class DeleteContentType extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ContentTypeService $contentTypeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Content type id is required.'];
    }

    return $this->contentTypeService->deleteContentType($id, $input['force'] ?? FALSE);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Content Type ID', 'required' => TRUE],
      'force' => ['type' => 'boolean', 'label' => 'Force Delete', 'required' => FALSE, 'default' => FALSE, 'description' => 'Delete even if content exists (dangerous!)'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Deleted Content Type ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
