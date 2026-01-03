<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\TaxonomyService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating taxonomy vocabularies.
 *
 * @Tool(
 *   id = "mcp_structure_create_vocabulary",
 *   label = @Translation("Create Vocabulary"),
 *   description = @Translation("Create a new taxonomy vocabulary."),
 *   category = "structure",
 * )
 */
class CreateVocabulary extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->taxonomyService->createVocabulary($id, $label, $input['description'] ?? '');
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE, 'description' => 'Lowercase, underscores (e.g., "tags")'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE, 'description' => 'Human-readable name (e.g., "Tags")'],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Vocabulary ID'],
      'label' => ['type' => 'string', 'label' => 'Vocabulary Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
