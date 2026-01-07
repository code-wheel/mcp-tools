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
  id: 'mcp_recipes_validate',
  label: new TranslatableMarkup('Validate Recipe'),
  description: new TranslatableMarkup('Validate a Drupal Recipe to check for errors before applying.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'recipe_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('The machine name (directory name) of the recipe to validate.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'recipe' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Recipe Name'),
      description: new TranslatableMarkup('Name of the validated recipe.'),
    ),
    'valid' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Is Valid'),
      description: new TranslatableMarkup('Whether the recipe passed validation.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('List of validation errors.'),
    ),
    'warnings' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Warnings'),
      description: new TranslatableMarkup('List of validation warnings.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of validation results.'),
    ),
  ],
)]
class ValidateRecipe extends McpToolsToolBase {

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

    return $this->recipesService->validateRecipe($recipeName);
  }

  

  

}
