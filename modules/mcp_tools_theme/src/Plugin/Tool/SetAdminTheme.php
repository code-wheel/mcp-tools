<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_theme\Service\ThemeService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for setting the admin theme.
 *
 * @Tool(
 *   id = "mcp_theme_set_admin",
 *   label = @Translation("Set Admin Theme"),
 *   description = @Translation("Set the administration theme for the site."),
 *   category = "theme",
 * )
 */
class SetAdminTheme extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->themeService->setAdminTheme($theme);
  }

  public function getInputDefinition(): array {
    return [
      'theme' => [
        'type' => 'string',
        'label' => 'Theme',
        'required' => TRUE,
        'description' => 'Machine name of the theme to set as admin theme',
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'theme' => ['type' => 'string', 'label' => 'Theme'],
      'previous_admin' => ['type' => 'string', 'label' => 'Previous Admin'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
      'changed' => ['type' => 'boolean', 'label' => 'Changed'],
    ];
  }

}
