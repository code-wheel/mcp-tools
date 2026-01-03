<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\TaxonomyService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating taxonomy terms.
 *
 * @Tool(
 *   id = "mcp_structure_create_term",
 *   label = @Translation("Create Term"),
 *   description = @Translation("Create a new taxonomy term in a vocabulary."),
 *   category = "structure",
 * )
 */
class CreateTerm extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  public function execute(array $input = []): array {
    $vocabulary = $input['vocabulary'] ?? '';
    $name = $input['name'] ?? '';

    if (empty($vocabulary) || empty($name)) {
      return ['success' => FALSE, 'error' => 'Both vocabulary and name are required.'];
    }

    $options = [];
    if (isset($input['description'])) $options['description'] = $input['description'];
    if (isset($input['parent'])) $options['parent'] = $input['parent'];
    if (isset($input['weight'])) $options['weight'] = $input['weight'];

    return $this->taxonomyService->createTerm($vocabulary, $name, $options);
  }

  public function getInputDefinition(): array {
    return [
      'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary', 'required' => TRUE, 'description' => 'Vocabulary machine name'],
      'name' => ['type' => 'string', 'label' => 'Term Name', 'required' => TRUE],
      'description' => ['type' => 'string', 'label' => 'Description', 'required' => FALSE],
      'parent' => ['type' => 'integer', 'label' => 'Parent Term ID', 'required' => FALSE, 'description' => 'For hierarchical terms'],
      'weight' => ['type' => 'integer', 'label' => 'Weight', 'required' => FALSE, 'default' => 0],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'tid' => ['type' => 'integer', 'label' => 'Term ID'],
      'name' => ['type' => 'string', 'label' => 'Term Name'],
      'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
