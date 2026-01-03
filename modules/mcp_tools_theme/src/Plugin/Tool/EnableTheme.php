<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for enabling/installing a theme.
 *
 * @Tool(
 *   id = "mcp_theme_enable",
 *   label = @Translation("Enable Theme"),
 *   description = @Translation("Enable/install a theme that is available but not yet installed."),
 *   category = "theme",
 * )
 */
class EnableTheme extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->themeService->enableTheme($theme);
  }

  public function getInputDefinition(): array {
    return [
      'theme' => [
        'type' => 'string',
        'label' => 'Theme',
        'required' => TRUE,
        'description' => 'Machine name of the theme to enable',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'label' => ['type' => 'string', 'label' => 'Theme Label'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
      'changed' => ['type' => 'boolean', 'label' => 'Changed'],
      'admin_path' => ['type' => 'string', 'label' => 'Admin Path'],
    ];
  }

}
