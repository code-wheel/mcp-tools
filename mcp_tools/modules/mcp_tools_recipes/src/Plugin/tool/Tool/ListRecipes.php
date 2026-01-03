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
  id: 'mcp_recipes_list',
  label: new TranslatableMarkup('List Recipes'),
  description: new TranslatableMarkup('List all available Drupal Recipes from site, core, and contrib sources.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'recipes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Recipes'),
      description: new TranslatableMarkup('List of available recipes with name, label, description, type, source, and path.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Recipe Count'),
      description: new TranslatableMarkup('Total number of available recipes.'),
    ),
    'sources' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Recipe Sources'),
      description: new TranslatableMarkup('List of directories where recipes were found.'),
    ),
  ],
)]
class ListRecipes extends McpToolsToolBase {

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
    return $this->recipesService->listRecipes();
  }

  

  

}
