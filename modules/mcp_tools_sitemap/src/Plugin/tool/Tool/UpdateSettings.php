<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\tool\Tool;

use Drupal\mcp_tools_sitemap\Service\SitemapService;
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
  id: 'mcp_sitemap_update_settings',
  label: new TranslatableMarkup('Update Sitemap Settings'),
  description: new TranslatableMarkup('Update settings for a sitemap variant. This is a write operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'variant' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sitemap Variant'),
      description: new TranslatableMarkup('The sitemap variant ID to update.'),
      required: TRUE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Settings to update. Can include: enabled (bool), label (string), global (object with max_links, cron_generate, etc.).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'variant' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Variant ID'),
      description: new TranslatableMarkup('The sitemap variant ID that was updated.'),
    ),
    'updated_settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updated Settings'),
      description: new TranslatableMarkup('The settings now in effect for this variant. Use Regenerate to rebuild the sitemap with new settings.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the settings update.'),
    ),
  ],
)]
class UpdateSettings extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'sitemap';


  /**
   * The sitemap service.
   *
   * @var \Drupal\mcp_tools_sitemap\Service\SitemapService
   */
  protected SitemapService $sitemapService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sitemapService = $container->get('mcp_tools_sitemap.sitemap');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $variant = $input['variant'] ?? '';
    $settings = $input['settings'] ?? [];

    if (empty($variant)) {
      return ['success' => FALSE, 'error' => 'Variant is required.'];
    }

    if (empty($settings)) {
      return ['success' => FALSE, 'error' => 'Settings object is required.'];
    }

    return $this->sitemapService->updateSettings($variant, $settings);
  }

}
