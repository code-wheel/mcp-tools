<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting paragraph types.
 *
 * @Tool(
 *   id = "mcp_paragraphs_delete_type",
 *   label = @Translation("Delete Paragraph Type"),
 *   description = @Translation("Delete a paragraph type. Will fail if paragraphs exist unless force=true."),
 *   category = "paragraphs",
 * )
 */
class DeleteParagraphType extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Paragraph type id is required.'];
    }

    return $this->paragraphsService->deleteParagraphType($id, $input['force'] ?? FALSE);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Paragraph Type ID', 'required' => TRUE],
      'force' => ['type' => 'boolean', 'label' => 'Force Delete', 'required' => FALSE, 'default' => FALSE, 'description' => 'Delete even if paragraphs exist (dangerous!)'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Deleted Paragraph Type ID'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
      'deleted_paragraphs' => ['type' => 'integer', 'label' => 'Deleted Paragraphs Count'],
    ];
  }

}
