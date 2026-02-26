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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_sitemap_list',
  label: new TranslatableMarkup('List Sitemaps'),
  description: new TranslatableMarkup('List all sitemap variants with their configuration and status.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Sitemaps'),
      description: new TranslatableMarkup('Total number of sitemap variants configured in the system.'),
    ),
    'sitemaps' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Sitemap Variants'),
      description: new TranslatableMarkup('Array of sitemap variant objects with id, label, enabled status, URL, and link count. Use variant ID with GetSettings or Regenerate.'),
    ),
  ],
)]
class ListSitemaps extends McpToolsToolBase {

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
    return $this->sitemapService->getSitemaps();
  }

}
