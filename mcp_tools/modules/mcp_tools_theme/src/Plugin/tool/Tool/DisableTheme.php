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
  id: 'mcp_theme_disable',
  label: new TranslatableMarkup('Disable Theme'),
  description: new TranslatableMarkup('Disable/uninstall a theme. Cannot disable the default or admin theme.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'theme' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup('Machine name of the theme to disable'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'theme' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Theme Label'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Changed'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class DisableTheme extends McpToolsToolBase {

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

    return $this->themeService->disableTheme($theme);
  }


}
