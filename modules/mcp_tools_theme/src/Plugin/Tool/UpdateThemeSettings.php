<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating theme settings.
 *
 * @Tool(
 *   id = "mcp_theme_update_settings",
 *   label = @Translation("Update Theme Settings"),
 *   description = @Translation("Update settings for a theme including logo, favicon, and color configurations."),
 *   category = "theme",
 * )
 */
class UpdateThemeSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'theme' => [
        'type' => 'string',
        'label' => 'Theme',
        'required' => TRUE,
        'description' => 'Machine name of the theme',
      ],
      'settings' => [
        'type' => 'object',
        'label' => 'Settings',
        'required' => TRUE,
        'description' => 'Settings to update. Examples: {"logo": {"use_default": false, "path": "public://logo.png"}, "favicon": {"use_default": false, "path": "public://favicon.ico"}}',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'updated_keys' => ['type' => 'list', 'label' => 'Updated Keys'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
