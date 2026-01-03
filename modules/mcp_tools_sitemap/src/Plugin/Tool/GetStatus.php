<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting sitemap generation status.
 *
 * @Tool(
 *   id = "mcp_sitemap_status",
 *   label = @Translation("Get Sitemap Status"),
 *   description = @Translation("Get sitemap generation status including last generated time, link counts, and queue status."),
 *   category = "sitemap",
 * )
 */
class GetStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->sitemapService->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'sitemaps' => ['type' => 'object', 'label' => 'Sitemap Status'],
      'total_sitemaps' => ['type' => 'integer', 'label' => 'Total Sitemaps'],
      'generator_queue' => ['type' => 'object', 'label' => 'Generator Queue Status'],
    ];
  }

}
