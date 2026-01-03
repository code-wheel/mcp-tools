<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting sitemap settings.
 *
 * @Tool(
 *   id = "mcp_sitemap_get_settings",
 *   label = @Translation("Get Sitemap Settings"),
 *   description = @Translation("Get settings for a specific sitemap variant including global settings and entity type configuration."),
 *   category = "sitemap",
 * )
 */
class GetSettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $variant = $input['variant'] ?? 'default';
    return $this->sitemapService->getSettings($variant);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'variant' => [
        'type' => 'string',
        'label' => 'Sitemap Variant',
        'description' => 'The sitemap variant ID (defaults to "default").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'variant' => ['type' => 'string', 'label' => 'Variant ID'],
      'label' => ['type' => 'string', 'label' => 'Variant Label'],
      'global_settings' => ['type' => 'object', 'label' => 'Global Settings'],
      'entity_types' => ['type' => 'object', 'label' => 'Entity Type Settings'],
    ];
  }

}
