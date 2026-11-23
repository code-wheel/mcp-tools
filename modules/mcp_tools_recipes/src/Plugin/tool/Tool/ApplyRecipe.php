<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Plugin\tool\Tool;

use Drupal\mcp_tools\Trait\WriteAccessTrait;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\AsksFirstInterface;
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
    'inputs' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Inputs'),
      description: new TranslatableMarkup('Values for the recipe\'s inputs, by name, e.g. {"site_name": "My site"}. An input not given gets its default. mcp_recipes_get shows a recipe\'s inputs.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'recipe' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Name of the applied recipe.'),
    ),
    'path' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Path'),
      description: new TranslatableMarkup('Filesystem path to the recipe.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Status message about the operation.'),
    ),
  ],
)]
class ApplyRecipe extends McpToolsToolBase implements AsksFirstInterface {

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

    return $this->recipesService->applyRecipe($recipeName, (array) ($input['inputs'] ?? []));
  }

  /**
   * {@inheritdoc}
   */
  public function reasonToAsk(array $arguments): ?string {
    $recipeName = $arguments['recipe_name'] ?? '';
    $unreviewed = is_string($recipeName) && $recipeName !== '' ? $this->recipesService->unreviewedIn($recipeName) : [];
    if ($unreviewed === []) {
      return NULL;
    }
    return sprintf(
      'The recipe "%s" would be applied. It was written through MCP and no person has reviewed it (%s). A recipe can change any config and grant any permission.',
      $recipeName,
      implode(', ', $unreviewed),
    );
  }

}
