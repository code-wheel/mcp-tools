<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting details of a specific Drupal Recipe.
 *
 * @Tool(
 *   id = "mcp_recipes_get",
 *   label = @Translation("Get Recipe"),
 *   description = @Translation("Get detailed information about a specific Drupal Recipe including modules, config, and dependencies."),
 *   category = "recipes",
 * )
 */
class GetRecipe extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The recipes service.
   */
  protected RecipesService $recipesService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->recipesService = $container->get('mcp_tools_recipes.recipes');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $recipeName = $input['recipe_name'] ?? '';

    if (empty($recipeName)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name is required.',
      ];
    }

    return $this->recipesService->getRecipe($recipeName);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'recipe_name' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'The machine name (directory name) of the recipe.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'name' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'Machine name of the recipe.',
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Recipe Label',
        'description' => 'Human-readable name of the recipe.',
      ],
      'description' => [
        'type' => 'string',
        'label' => 'Description',
        'description' => 'Recipe description.',
      ],
      'type' => [
        'type' => 'string',
        'label' => 'Type',
        'description' => 'Recipe type (e.g., Site, Theme).',
      ],
      'path' => [
        'type' => 'string',
        'label' => 'Path',
        'description' => 'Filesystem path to the recipe.',
      ],
      'install' => [
        'type' => 'array',
        'label' => 'Modules to Install',
        'description' => 'List of modules that will be installed.',
      ],
      'config' => [
        'type' => 'map',
        'label' => 'Configuration',
        'description' => 'Configuration imports and actions.',
      ],
      'recipes' => [
        'type' => 'array',
        'label' => 'Recipe Dependencies',
        'description' => 'Other recipes this recipe depends on.',
      ],
      'files' => [
        'type' => 'array',
        'label' => 'Recipe Files',
        'description' => 'List of files in the recipe directory.',
      ],
    ];
  }

}
