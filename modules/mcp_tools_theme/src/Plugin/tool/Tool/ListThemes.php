<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\tool\Tool;

use Drupal\mcp_tools_theme\Service\ThemeService;
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
  id: 'mcp_theme_list',
  label: new TranslatableMarkup('List Themes'),
  description: new TranslatableMarkup('List all installed themes. Optionally include uninstalled themes.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'include_uninstalled' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Include Uninstalled'),
      description: new TranslatableMarkup('Include themes that are available but not installed'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'themes' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Themes'),
      description: new TranslatableMarkup(''),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Themes'),
      description: new TranslatableMarkup(''),
    ),
    'default_theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Default Theme'),
      description: new TranslatableMarkup(''),
    ),
    'admin_theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Theme'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ListThemes extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'theme';


  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $includeUninstalled = (bool) ($input['include_uninstalled'] ?? FALSE);
    return $this->themeService->listThemes($includeUninstalled);
  }


}
