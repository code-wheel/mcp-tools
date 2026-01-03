<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing available field types.
 *
 * @Tool(
 *   id = "mcp_structure_list_field_types",
 *   label = @Translation("List Field Types"),
 *   description = @Translation("List available field types that can be added to entities."),
 *   category = "structure",
 * )
 */
class ListFieldTypes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected FieldService $fieldService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fieldService = $container->get('mcp_tools_structure.field');
    return $instance;
  }

  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->fieldService->getFieldTypes(),
    ];
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'types' => ['type' => 'list', 'label' => 'Field Types'],
      'total' => ['type' => 'integer', 'label' => 'Total Types'],
    ];
  }

}
