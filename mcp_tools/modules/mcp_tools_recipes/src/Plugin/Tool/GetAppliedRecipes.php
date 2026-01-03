<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting a list of recipes that have been applied to the site.
 *
 * @Tool(
 *   id = "mcp_recipes_applied",
 *   label = @Translation("Get Applied Recipes"),
 *   description = @Translation("List all Drupal Recipes that have been applied to this site."),
 *   category = "recipes",
 * )
 */
class GetAppliedRecipes extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->recipesService->getAppliedRecipes();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'recipes' => [
        'type' => 'array',
        'label' => 'Applied Recipes',
        'description' => 'List of recipes that have been applied, with name, path, applied_at timestamp, and applied_by user.',
      ],
      'count' => [
        'type' => 'integer',
        'label' => 'Recipe Count',
        'description' => 'Total number of applied recipes.',
      ],
    ];
  }

}
