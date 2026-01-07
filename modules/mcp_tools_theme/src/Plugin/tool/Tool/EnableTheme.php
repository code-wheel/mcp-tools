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
  id: 'mcp_theme_enable',
  label: new TranslatableMarkup('Enable Theme'),
  description: new TranslatableMarkup('Enable/install a theme that is available but not yet installed.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme to enable'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the enabled theme. Use SetDefaultTheme or SetAdminTheme to make it active.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme Label'),
      description: new TranslatableMarkup('Human-readable name of the enabled theme.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the theme installation.'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Changed'),
      description: new TranslatableMarkup('TRUE if the theme was enabled, FALSE if it was already enabled.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to the theme settings page in admin UI (e.g., "/admin/appearance/settings/themename").'),
    ),
  ],
)]
class EnableTheme extends McpToolsToolBase {

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

    return $this->themeService->enableTheme($theme);
  }


}
