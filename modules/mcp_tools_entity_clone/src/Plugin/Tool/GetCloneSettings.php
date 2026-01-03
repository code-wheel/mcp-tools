<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_entity_clone\Service\EntityCloneService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting clone settings for a specific entity bundle.
 *
 * @Tool(
 *   id = "mcp_entity_clone_settings",
 *   label = @Translation("Get Clone Settings"),
 *   description = @Translation("Get clone settings and reference fields for a specific entity type and bundle."),
 *   category = "entity_clone",
 * )
 */
class GetCloneSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected EntityCloneService $entityCloneService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityCloneService = $container->get('mcp_tools_entity_clone.entity_clone');
    return $instance;
  }

  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? '';

    if (empty($entityType) || empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Both entity_type and bundle are required.'];
    }

    return $this->entityCloneService->getCloneSettings($entityType, $bundle);
  }

  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type (e.g., node, media)',
        'required' => TRUE,
      ],
      'bundle' => [
        'type' => 'string',
        'label' => 'Bundle',
        'description' => 'The bundle/content type machine name (e.g., article, page)',
        'required' => TRUE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'settings' => ['type' => 'object', 'label' => 'Clone Settings'],
      'reference_fields' => ['type' => 'array', 'label' => 'Reference Fields'],
      'paragraph_fields' => ['type' => 'array', 'label' => 'Paragraph Fields'],
      'has_paragraphs' => ['type' => 'boolean', 'label' => 'Has Paragraphs'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
