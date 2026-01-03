<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Trait\WriteAccessTrait;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for applying a Drupal Recipe to the site.
 *
 * This is a potentially dangerous operation that can make significant changes
 * to your site configuration. Requires admin scope.
 *
 * @Tool(
 *   id = "mcp_recipes_apply",
 *   label = @Translation("Apply Recipe"),
 *   description = @Translation("Apply a Drupal Recipe to configure the site. WARNING: This can make significant changes. Requires admin scope."),
 *   category = "recipes",
 * )
 */
class ApplyRecipe extends ToolPluginBase implements ContainerFactoryPluginInterface {

  use WriteAccessTrait;

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
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Require admin scope for this operation.
    $accessDenied = $this->checkAdminAccess();
    if ($accessDenied) {
      return $accessDenied;
    }

    $recipeName = $input['recipe_name'] ?? '';

    if (empty($recipeName)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name is required.',
      ];
    }

    return $this->recipesService->applyRecipe($recipeName);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'recipe_name' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'The machine name (directory name) of the recipe to apply.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'recipe' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'Name of the applied recipe.',
      ],
      'path' => [
        'type' => 'string',
        'label' => 'Recipe Path',
        'description' => 'Filesystem path to the recipe.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result Message',
        'description' => 'Status message about the operation.',
      ],
    ];
  }

}
