<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all sitemap variants.
 *
 * @Tool(
 *   id = "mcp_sitemap_list",
 *   label = @Translation("List Sitemaps"),
 *   description = @Translation("List all sitemap variants with their configuration and status."),
 *   category = "sitemap",
 * )
 */
class ListSitemaps extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->sitemapService->getSitemaps();
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
      'total' => ['type' => 'integer', 'label' => 'Total Sitemaps'],
      'sitemaps' => ['type' => 'array', 'label' => 'Sitemap Variants'],
    ];
  }

}
