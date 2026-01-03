<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing available Drupal Recipes.
 *
 * @Tool(
 *   id = "mcp_recipes_list",
 *   label = @Translation("List Recipes"),
 *   description = @Translation("List all available Drupal Recipes from site, core, and contrib sources."),
 *   category = "recipes",
 * )
 */
class ListRecipes extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->recipesService->listRecipes();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required for listing recipes.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'recipes' => [
        'type' => 'array',
        'label' => 'Available Recipes',
        'description' => 'List of available recipes with name, label, description, type, source, and path.',
      ],
      'count' => [
        'type' => 'integer',
        'label' => 'Recipe Count',
        'description' => 'Total number of available recipes.',
      ],
      'sources' => [
        'type' => 'array',
        'label' => 'Recipe Sources',
        'description' => 'List of directories where recipes were found.',
      ],
    ];
  }

}
