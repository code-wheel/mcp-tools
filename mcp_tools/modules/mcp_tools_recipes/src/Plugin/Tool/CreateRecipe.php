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
 * Tool for creating a new Drupal Recipe.
 *
 * @Tool(
 *   id = "mcp_recipes_create",
 *   label = @Translation("Create Recipe"),
 *   description = @Translation("Create a new Drupal Recipe file in the site's recipes directory. Requires admin scope."),
 *   category = "recipes",
 * )
 */
class CreateRecipe extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';

    if (empty($name)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name is required.',
      ];
    }

    if (empty($description)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe description is required.',
      ];
    }

    // Build config array from input.
    $config = [];

    if (!empty($input['label'])) {
      $config['label'] = $input['label'];
    }

    if (!empty($input['type'])) {
      $config['type'] = $input['type'];
    }

    if (!empty($input['install'])) {
      // Handle both array and comma-separated string.
      $config['install'] = is_array($input['install'])
        ? $input['install']
        : array_map('trim', explode(',', $input['install']));
    }

    if (!empty($input['recipes'])) {
      // Handle both array and comma-separated string.
      $config['recipes'] = is_array($input['recipes'])
        ? $input['recipes']
        : array_map('trim', explode(',', $input['recipes']));
    }

    if (!empty($input['config'])) {
      $config['config'] = $input['config'];
    }

    return $this->recipesService->createRecipe($name, $description, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'name' => [
        'type' => 'string',
        'label' => 'Recipe Name',
        'description' => 'Machine name for the recipe (lowercase, underscores allowed).',
        'required' => TRUE,
      ],
      'description' => [
        'type' => 'string',
        'label' => 'Description',
        'description' => 'Human-readable description of what the recipe does.',
        'required' => TRUE,
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'Human-readable name for the recipe (defaults to formatted machine name).',
        'required' => FALSE,
      ],
      'type' => [
        'type' => 'string',
        'label' => 'Type',
        'description' => 'Recipe type (e.g., Site, Theme). Defaults to "Site".',
        'required' => FALSE,
      ],
      'install' => [
        'type' => 'array',
        'label' => 'Modules to Install',
        'description' => 'Array or comma-separated list of modules to install.',
        'required' => FALSE,
      ],
      'recipes' => [
        'type' => 'array',
        'label' => 'Recipe Dependencies',
        'description' => 'Array or comma-separated list of recipes this recipe depends on.',
        'required' => FALSE,
      ],
      'config' => [
        'type' => 'object',
        'label' => 'Configuration',
        'description' => 'Configuration object with import and actions sections.',
        'required' => FALSE,
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
        'description' => 'Machine name of the created recipe.',
      ],
      'path' => [
        'type' => 'string',
        'label' => 'Recipe Path',
        'description' => 'Filesystem path to the created recipe.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result Message',
        'description' => 'Status message about the operation.',
      ],
    ];
  }

}
