<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tool\ToolPluginBase;
use Drupal\tool\ToolPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for discovering available MCP tools.
 *
 * This meta-tool helps AI assistants understand what tools are available
 * and find the right tool for a given task.
 *
 * @Tool(
 *   id = "mcp_tools_list_available",
 *   label = @Translation("List Available Tools"),
 *   description = @Translation("List all available MCP tools, optionally filtered by category or search term. Use this to discover what operations are available."),
 *   category = "discovery",
 * )
 */
class ListAvailableTools extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The tool plugin manager.
   */
  protected ToolPluginManager $toolManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->toolManager = $container->get('plugin.manager.tool');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $category = $input['category'] ?? NULL;
    $search = $input['search'] ?? NULL;
    $includeDescriptions = $input['include_descriptions'] ?? TRUE;

    $definitions = $this->toolManager->getDefinitions();
    $tools = [];
    $categories = [];

    foreach ($definitions as $id => $definition) {
      // Only include MCP tools.
      if (!str_starts_with($id, 'mcp_')) {
        continue;
      }

      $toolCategory = $definition['category'] ?? 'other';

      // Filter by category if specified.
      if ($category && strtolower($toolCategory) !== strtolower($category)) {
        continue;
      }

      // Filter by search term if specified.
      if ($search) {
        $searchLower = strtolower($search);
        $idMatch = str_contains(strtolower($id), $searchLower);
        $labelMatch = str_contains(strtolower((string) $definition['label']), $searchLower);
        $descMatch = str_contains(strtolower((string) $definition['description']), $searchLower);

        if (!$idMatch && !$labelMatch && !$descMatch) {
          continue;
        }
      }

      $toolInfo = [
        'id' => $id,
        'label' => (string) $definition['label'],
        'category' => $toolCategory,
      ];

      if ($includeDescriptions) {
        $toolInfo['description'] = (string) $definition['description'];
      }

      $tools[] = $toolInfo;

      // Track categories.
      if (!isset($categories[$toolCategory])) {
        $categories[$toolCategory] = 0;
      }
      $categories[$toolCategory]++;
    }

    // Sort tools by category then by ID.
    usort($tools, function ($a, $b) {
      $catCmp = strcmp($a['category'], $b['category']);
      return $catCmp !== 0 ? $catCmp : strcmp($a['id'], $b['id']);
    });

    // Sort categories by count descending.
    arsort($categories);

    return [
      'success' => TRUE,
      'data' => [
        'total_tools' => count($tools),
        'categories' => $categories,
        'tools' => $tools,
        'filters_applied' => [
          'category' => $category,
          'search' => $search,
        ],
        'hint' => $this->getUsageHint($category, $search),
      ],
    ];
  }

  /**
   * Get a usage hint based on the filter applied.
   */
  protected function getUsageHint(?string $category, ?string $search): string {
    if (!$category && !$search) {
      return 'Use category or search filters to narrow down results. Common categories: content, structure, users, menus, views, analysis.';
    }

    if ($category) {
      return "Showing tools in the '$category' category. These tools handle related operations.";
    }

    return "Showing tools matching '$search'. Use the tool ID to call the desired operation.";
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'category' => [
        'type' => 'string',
        'label' => 'Category',
        'description' => 'Filter by category (e.g., content, structure, users, analysis, cache, cron).',
        'required' => FALSE,
      ],
      'search' => [
        'type' => 'string',
        'label' => 'Search',
        'description' => 'Search term to filter tools by ID, label, or description.',
        'required' => FALSE,
      ],
      'include_descriptions' => [
        'type' => 'boolean',
        'label' => 'Include Descriptions',
        'description' => 'Include tool descriptions in output (default: true).',
        'required' => FALSE,
        'default' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_tools' => [
        'type' => 'integer',
        'label' => 'Total Tools',
      ],
      'categories' => [
        'type' => 'map',
        'label' => 'Categories',
      ],
      'tools' => [
        'type' => 'list',
        'label' => 'Tools',
      ],
    ];
  }

}
