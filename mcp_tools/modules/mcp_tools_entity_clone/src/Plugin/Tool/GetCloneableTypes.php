<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing entity types that can be cloned.
 *
 * @Tool(
 *   id = "mcp_entity_clone_types",
 *   label = @Translation("Get Cloneable Entity Types"),
 *   description = @Translation("List all entity types that support cloning with their bundles."),
 *   category = "entity_clone",
 * )
 */
class GetCloneableTypes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->entityCloneService->getCloneableTypes();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'types' => ['type' => 'array', 'label' => 'Cloneable Entity Types'],
      'total' => ['type' => 'integer', 'label' => 'Total Types'],
    ];
  }

}
