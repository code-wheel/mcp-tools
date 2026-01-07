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
  id: 'mcp_theme_get_settings',
  label: new TranslatableMarkup('Get Theme Settings'),
  description: new TranslatableMarkup('Get settings for a specific theme including logo, favicon, and other configurations.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme whose settings are returned.'),
    ),
    'settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Theme Settings'),
      description: new TranslatableMarkup('Theme-specific settings including features, colors, and custom options. Use UpdateThemeSettings to modify.'),
    ),
    'global_settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Global Settings'),
      description: new TranslatableMarkup('Global settings that apply to all themes: features, toggles, and system defaults.'),
    ),
    'logo' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Logo Settings'),
      description: new TranslatableMarkup('Logo configuration: use_default (bool), path (string). Modify via UpdateThemeSettings with logo key.'),
    ),
    'favicon' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Favicon Settings'),
      description: new TranslatableMarkup('Favicon configuration: use_default (bool), path (string), mimetype. Modify via UpdateThemeSettings with favicon key.'),
    ),
  ],
)]
class GetThemeSettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'theme';


  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $theme = $input['theme'] ?? '';

    if (empty($theme)) {
      return ['success' => FALSE, 'error' => 'Theme name is required.'];
    }

    return $this->themeService->getThemeSettings($theme);
  }


}
