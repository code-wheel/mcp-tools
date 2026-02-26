<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\tool\Tool;

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
  id: 'mcp_recipes_apply',
  label: new TranslatableMarkup('Apply Recipe'),
  description: new TranslatableMarkup('Apply a Drupal Recipe to configure the site. WARNING: This can make significant changes. Requires admin scope.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'recipe_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('The machine name (directory name) of the recipe to apply.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'recipe' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Name of the applied recipe.'),
    ),
    'path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Path'),
      description: new TranslatableMarkup('Filesystem path to the recipe.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Status message about the operation.'),
    ),
  ],
)]
class ApplyRecipe extends McpToolsToolBase {

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

    $recipeName = $input['recipe_name'] ?? '';

    if (empty($recipeName)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name is required.',
      ];
    }

    return $this->recipesService->applyRecipe($recipeName);
  }

}
