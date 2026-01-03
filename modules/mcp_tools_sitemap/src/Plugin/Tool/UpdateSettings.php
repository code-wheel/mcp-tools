<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating sitemap settings.
 *
 * @Tool(
 *   id = "mcp_sitemap_update_settings",
 *   label = @Translation("Update Sitemap Settings"),
 *   description = @Translation("Update settings for a sitemap variant. This is a write operation."),
 *   category = "sitemap",
 * )
 */
class UpdateSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SitemapService $sitemapService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->sitemapService = $container->get('mcp_tools_sitemap.sitemap');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'variant' => [
        'type' => 'string',
        'label' => 'Sitemap Variant',
        'description' => 'The sitemap variant ID to update.',
        'required' => TRUE,
      ],
      'settings' => [
        'type' => 'object',
        'label' => 'Settings',
        'description' => 'Settings to update. Can include: enabled (bool), label (string), global (object with max_links, cron_generate, etc.).',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'variant' => ['type' => 'string', 'label' => 'Variant ID'],
      'updated_settings' => ['type' => 'object', 'label' => 'Updated Settings'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
