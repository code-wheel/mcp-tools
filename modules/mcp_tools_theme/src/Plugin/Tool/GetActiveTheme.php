<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting the current active theme information.
 *
 * @Tool(
 *   id = "mcp_theme_get_active",
 *   label = @Translation("Get Active Theme"),
 *   description = @Translation("Get information about the current active theme, default theme, and admin theme."),
 *   category = "theme",
 * )
 */
class GetActiveTheme extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  public function execute(array $input = []): array {
    return $this->themeService->getActiveTheme();
  }

  public function getInputDefinition(): array {
    return [];
  }

  public function getOutputDefinition(): array {
    return [
      'active_theme' => ['type' => 'string', 'label' => 'Active Theme'],
      'default_theme' => ['type' => 'string', 'label' => 'Default Theme'],
      'admin_theme' => ['type' => 'string', 'label' => 'Admin Theme'],
      'active_theme_info' => ['type' => 'object', 'label' => 'Active Theme Info'],
    ];
  }

}
