<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_list_available',
  label: new TranslatableMarkup('List Available Tools'),
  description: new TranslatableMarkup('List all available MCP tools, optionally filtered by category or search term. Use this to discover what operations are available.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'category' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Category'),
      description: new TranslatableMarkup('Filter by category (e.g., content, structure, users, analysis, cache, cron).'),
      required: FALSE,
    ),
    'search' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search'),
      description: new TranslatableMarkup('Search term to filter tools by ID, label, or description.'),
      required: FALSE,
    ),
    'include_descriptions' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Include Descriptions'),
      description: new TranslatableMarkup('Include tool descriptions in output (default: true).'),
      required: FALSE,
      default_value: TRUE,
    ),
  ],
  output_definitions: [
    'total_tools' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Tools'),
      description: new TranslatableMarkup('Number of tools matching the filters.'),
    ),
    'categories' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Categories'),
      description: new TranslatableMarkup('Tool counts by category. Use category names to filter further.'),
    ),
    'tools' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Tools'),
      description: new TranslatableMarkup('Array of tools with id, label, category, and description. Use id to call the tool.'),
    ),
  ],
)]
class ListAvailableTools extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'discovery';


  /**
   * The tool plugin manager.
   */
  protected ToolManager $toolManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->toolManager = $container->get('plugin.manager.tool');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $category = $input['category'] ?? NULL;
    $search = $input['search'] ?? NULL;
    $includeDescriptions = $input['include_descriptions'] ?? TRUE;

    $definitions = $this->toolManager->getDefinitions();
    $tools = [];
    $categories = [];

    foreach ($definitions as $id => $definition) {
      if (!$definition instanceof ToolDefinition) {
        continue;
      }

      // Only include MCP tools.
      if (!str_starts_with($id, 'mcp_')) {
        continue;
      }

      $toolCategory = $this->getToolCategory($definition);

      // Filter by category if specified.
      if ($category && strtolower($toolCategory) !== strtolower($category)) {
        continue;
      }

      // Filter by search term if specified.
      if ($search) {
        $searchLower = strtolower($search);
        $idMatch = str_contains(strtolower($id), $searchLower);
        $labelMatch = str_contains(strtolower((string) $definition->getLabel()), $searchLower);
        $descMatch = str_contains(strtolower((string) $definition->getDescription()), $searchLower);

        if (!$idMatch && !$labelMatch && !$descMatch) {
          continue;
        }
      }

      $toolInfo = [
        'id' => $id,
        'label' => (string) $definition->getLabel(),
        'category' => $toolCategory,
      ];

      if ($includeDescriptions) {
        $toolInfo['description'] = (string) $definition->getDescription();
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
   * Resolve an MCP Tools category for a Tool API definition.
   */
  protected function getToolCategory(ToolDefinition $definition): string {
    $class = $definition->getClass();
    if (!is_string($class) || $class === '') {
      return 'other';
    }

    $const = $class . '::MCP_CATEGORY';
    if (class_exists($class) && defined($const)) {
      $category = constant($const);
      if (is_string($category) && $category !== '') {
        return $category;
      }
    }

    return 'other';
  }

}
