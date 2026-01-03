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
  id: 'mcp_theme_get_active',
  label: new TranslatableMarkup('Get Active Theme'),
  description: new TranslatableMarkup('Get information about the current active theme, default theme, and admin theme.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'active_theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Active Theme'),
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
    'active_theme_info' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Active Theme Info'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetActiveTheme extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'theme';


  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    return $this->themeService->getActiveTheme();
  }


}
