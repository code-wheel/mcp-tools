<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for validating a Drupal Recipe before applying.
 *
 * @Tool(
 *   id = "mcp_recipes_validate",
 *   label = @Translation("Validate Recipe"),
 *   description = @Translation("Validate a Drupal Recipe to check for errors before applying."),
 *   category = "recipes",
 * )
 */
class ValidateRecipe extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->recipesService->validateRecipe($recipeName);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'recipe_name' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'The machine name (directory name) of the recipe to validate.',
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
        'description' => 'Name of the validated recipe.',
      ],
      'valid' => [
        'type' => 'boolean',
        'label' => 'Is Valid',
        'description' => 'Whether the recipe passed validation.',
      ],
      'errors' => [
        'type' => 'array',
        'label' => 'Errors',
        'description' => 'List of validation errors.',
      ],
      'warnings' => [
        'type' => 'array',
        'label' => 'Warnings',
        'description' => 'List of validation warnings.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result Message',
        'description' => 'Summary of validation results.',
      ],
    ];
  }

}
