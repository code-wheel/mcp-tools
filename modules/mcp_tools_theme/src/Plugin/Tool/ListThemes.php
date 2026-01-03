<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all themes.
 *
 * @Tool(
 *   id = "mcp_theme_list",
 *   label = @Translation("List Themes"),
 *   description = @Translation("List all installed themes. Optionally include uninstalled themes."),
 *   category = "theme",
 * )
 */
class ListThemes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ThemeService $themeService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->themeService = $container->get('mcp_tools_theme.theme');
    return $instance;
  }

  public function execute(array $input = []): array {
    $includeUninstalled = (bool) ($input['include_uninstalled'] ?? FALSE);
    return $this->themeService->listThemes($includeUninstalled);
  }

  public function getInputDefinition(): array {
    return [
      'include_uninstalled' => [
        'type' => 'boolean',
        'label' => 'Include Uninstalled',
        'required' => FALSE,
        'description' => 'Include themes that are available but not installed',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'themes' => ['type' => 'list', 'label' => 'Themes'],
      'total' => ['type' => 'integer', 'label' => 'Total Themes'],
      'default_theme' => ['type' => 'string', 'label' => 'Default Theme'],
      'admin_theme' => ['type' => 'string', 'label' => 'Admin Theme'],
    ];
  }

}
