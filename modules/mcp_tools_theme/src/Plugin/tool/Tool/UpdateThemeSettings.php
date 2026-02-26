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
  id: 'mcp_theme_update_settings',
  label: new TranslatableMarkup('Update Theme Settings'),
  description: new TranslatableMarkup('Update settings for a theme including logo, favicon, and color configurations.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme'),
      required: TRUE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Settings to update. Examples: {"logo": {"use_default": false, "path": "public://logo.png"}, "favicon": {"use_default": false, "path": "public://favicon.ico"}}'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme whose settings were updated.'),
    ),
    'updated_keys' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated Keys'),
      description: new TranslatableMarkup('List of setting keys that were modified (e.g., ["logo", "favicon"]). Use GetThemeSettings to verify changes.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of which settings were updated.'),
    ),
  ],
)]
class UpdateThemeSettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'theme';


  /**
   * The theme service.
   *
   * @var \Drupal\mcp_tools_theme\Service\ThemeService
   */
  protected ThemeService $themeService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $theme = $input['theme'] ?? '';
    $settings = $input['settings'] ?? [];

    if (empty($theme)) {
      return ['success' => FALSE, 'error' => 'Theme name is required.'];
    }

    if (empty($settings)) {
      return ['success' => FALSE, 'error' => 'At least one setting is required.'];
    }

    return $this->themeService->updateThemeSettings($theme, $settings);
  }

}
