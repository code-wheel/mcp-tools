<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\TaxonomyService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating multiple taxonomy terms at once.
 *
 * @Tool(
 *   id = "mcp_structure_create_terms",
 *   label = @Translation("Create Multiple Terms"),
 *   description = @Translation("Create multiple taxonomy terms in a vocabulary at once."),
 *   category = "structure",
 * )
 */
class CreateTerms extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  public function execute(array $input = []): array {
    $vocabulary = $input['vocabulary'] ?? '';
    $terms = $input['terms'] ?? [];

    if (empty($vocabulary)) {
      return ['success' => FALSE, 'error' => 'Vocabulary is required.'];
    }

    if (empty($terms)) {
      return ['success' => FALSE, 'error' => 'At least one term is required.'];
    }

    return $this->taxonomyService->createTerms($vocabulary, $terms);
  }

  public function getInputDefinition(): array {
    return [
      'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary', 'required' => TRUE],
      'terms' => ['type' => 'list', 'label' => 'Terms', 'required' => TRUE, 'description' => 'Array of term names or objects with name/description/parent'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary'],
      'created_count' => ['type' => 'integer', 'label' => 'Terms Created'],
      'error_count' => ['type' => 'integer', 'label' => 'Errors'],
      'created' => ['type' => 'list', 'label' => 'Created Terms'],
      'errors' => ['type' => 'list', 'label' => 'Errors'],
    ];
  }

}
