<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\tool\Tool;

use Drupal\mcp_tools_recipes\Service\RecipesService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_recipes_get',
  label: new TranslatableMarkup('Get Recipe'),
  description: new TranslatableMarkup('Get detailed information about a specific Drupal Recipe including modules, config, and dependencies.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'recipe_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('The machine name (directory name) of the recipe.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Machine name of the recipe.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Label'),
      description: new TranslatableMarkup('Human-readable name of the recipe.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Recipe description.'),
    ),
    'type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Type'),
      description: new TranslatableMarkup('Recipe type (e.g., Site, Theme).'),
    ),
    'path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Path'),
      description: new TranslatableMarkup('Filesystem path to the recipe.'),
    ),
    'install' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Modules to Install'),
      description: new TranslatableMarkup('List of modules that will be installed.'),
    ),
    'config' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Configuration'),
      description: new TranslatableMarkup('Configuration imports and actions.'),
    ),
    'recipes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Recipe Dependencies'),
      description: new TranslatableMarkup('Other recipes this recipe depends on.'),
    ),
    'files' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Recipe Files'),
      description: new TranslatableMarkup('List of files in the recipe directory.'),
    ),
  ],
)]
class GetRecipe extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'recipes';


  /**
   * The recipes service.
   */
  protected RecipesService $recipesService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->recipesService = $container->get('mcp_tools_recipes.recipes');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $recipeName = $input['recipe_name'] ?? '';

    if (empty($recipeName)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name is required.',
      ];
    }

    return $this->recipesService->getRecipe($recipeName);
  }

}
