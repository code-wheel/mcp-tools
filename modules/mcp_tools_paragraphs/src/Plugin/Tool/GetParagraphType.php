<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting details of a paragraph type.
 *
 * @Tool(
 *   id = "mcp_paragraphs_get_type",
 *   label = @Translation("Get Paragraph Type"),
 *   description = @Translation("Get details of a specific paragraph type including its fields."),
 *   category = "paragraphs",
 * )
 */
class GetParagraphType extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->paragraphsService->getParagraphType($id);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Paragraph Type ID', 'required' => TRUE, 'description' => 'Machine name of the paragraph type'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Paragraph Type ID'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'description' => ['type' => 'string', 'label' => 'Description'],
      'fields' => ['type' => 'list', 'label' => 'Fields'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
    ];
  }

}
