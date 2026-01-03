<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting theme settings.
 *
 * @Tool(
 *   id = "mcp_theme_get_settings",
 *   label = @Translation("Get Theme Settings"),
 *   description = @Translation("Get settings for a specific theme including logo, favicon, and other configurations."),
 *   category = "theme",
 * )
 */
class GetThemeSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  public function execute(array $input = []): array {
    $theme = $input['theme'] ?? '';

    if (empty($theme)) {
      return ['success' => FALSE, 'error' => 'Theme name is required.'];
    }

    return $this->themeService->getThemeSettings($theme);
  }

  public function getInputDefinition(): array {
    return [
      'theme' => [
        'type' => 'string',
        'label' => 'Theme',
        'required' => TRUE,
        'description' => 'Machine name of the theme',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'settings' => ['type' => 'object', 'label' => 'Theme Settings'],
      'global_settings' => ['type' => 'object', 'label' => 'Global Settings'],
      'logo' => ['type' => 'object', 'label' => 'Logo Settings'],
      'favicon' => ['type' => 'object', 'label' => 'Favicon Settings'],
    ];
  }

}
