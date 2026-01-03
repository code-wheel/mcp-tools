<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Trait\WriteAccessTrait;
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
  id: 'mcp_recipes_create',
  label: new TranslatableMarkup('Create Recipe'),
  description: new TranslatableMarkup('Create a new Drupal Recipe file in the site\'s recipes directory. Requires admin scope.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Machine name for the recipe (lowercase, underscores allowed).'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Human-readable description of what the recipe does.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name for the recipe (defaults to formatted machine name).'),
      required: FALSE,
    ),
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Type'),
      description: new TranslatableMarkup('Recipe type (e.g., Site, Theme). Defaults to "Site".'),
      required: FALSE,
    ),
    'install' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Modules to Install'),
      description: new TranslatableMarkup('Array or comma-separated list of modules to install.'),
      required: FALSE,
    ),
    'recipes' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Recipe Dependencies'),
      description: new TranslatableMarkup('Array or comma-separated list of recipes this recipe depends on.'),
      required: FALSE,
    ),
    'config' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Configuration'),
      description: new TranslatableMarkup('Configuration object with import and actions sections.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Machine name of the created recipe.'),
    ),
    'path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Path'),
      description: new TranslatableMarkup('Filesystem path to the created recipe.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Status message about the operation.'),
    ),
  ],
)]
class CreateRecipe extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'recipes';


  use WriteAccessTrait;

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
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

  

  

}
