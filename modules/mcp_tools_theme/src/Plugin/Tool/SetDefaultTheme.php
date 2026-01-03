<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for setting the default theme.
 *
 * @Tool(
 *   id = "mcp_theme_set_default",
 *   label = @Translation("Set Default Theme"),
 *   description = @Translation("Set the default frontend theme for the site."),
 *   category = "theme",
 * )
 */
class SetDefaultTheme extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->themeService->setDefaultTheme($theme);
  }

  public function getInputDefinition(): array {
    return [
      'theme' => [
        'type' => 'string',
        'label' => 'Theme',
        'required' => TRUE,
        'description' => 'Machine name of the theme to set as default',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'previous_default' => ['type' => 'string', 'label' => 'Previous Default'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
      'changed' => ['type' => 'boolean', 'label' => 'Changed'],
    ];
  }

}
