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
  id: 'mcp_theme_set_admin',
  label: new TranslatableMarkup('Set Admin Theme'),
  description: new TranslatableMarkup('Set the administration theme for the site.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme to set as admin theme'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the new admin theme. Use GetThemeSettings to view its configuration.'),
    ),
    'previous_admin' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Previous Admin'),
      description: new TranslatableMarkup('Machine name of the previous admin theme, useful for reverting if needed.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the admin theme change.'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Changed'),
      description: new TranslatableMarkup('TRUE if the admin theme was changed, FALSE if the theme was already the admin theme.'),
    ),
  ],
)]
class SetAdminTheme extends McpToolsToolBase {

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

    if (empty($theme)) {
      return ['success' => FALSE, 'error' => 'Theme name is required.'];
    }

    return $this->themeService->setAdminTheme($theme);
  }

}
