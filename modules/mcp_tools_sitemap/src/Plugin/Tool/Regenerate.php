<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for regenerating sitemaps.
 *
 * @Tool(
 *   id = "mcp_sitemap_regenerate",
 *   label = @Translation("Regenerate Sitemap"),
 *   description = @Translation("Regenerate sitemap(s). Can regenerate all sitemaps or a specific variant. This is a write operation."),
 *   category = "sitemap",
 * )
 */
class Regenerate extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $variant = $input['variant'] ?? NULL;
    return $this->sitemapService->regenerate($variant);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'variant' => [
        'type' => 'string',
        'label' => 'Sitemap Variant',
        'description' => 'Optional: specific sitemap variant to regenerate. If not provided, all variants will be regenerated.',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'variant' => ['type' => 'string', 'label' => 'Regenerated Variant'],
      'queue_status' => ['type' => 'object', 'label' => 'Queue Status'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
