<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_paragraphs\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_paragraphs\Service\ParagraphsService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing paragraph types.
 *
 * @Tool(
 *   id = "mcp_paragraphs_list_types",
 *   label = @Translation("List Paragraph Types"),
 *   description = @Translation("List all paragraph types with their fields."),
 *   category = "paragraphs",
 * )
 */
class ListParagraphTypes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ParagraphsService $paragraphsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->paragraphsService = $container->get('mcp_tools_paragraphs.paragraphs');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->paragraphsService->listParagraphTypes();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'types' => ['type' => 'list', 'label' => 'Paragraph Types'],
      'total' => ['type' => 'integer', 'label' => 'Total Types'],
    ];
  }

}
