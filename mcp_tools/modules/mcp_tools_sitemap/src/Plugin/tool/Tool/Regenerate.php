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
  id: 'mcp_sitemap_regenerate',
  label: new TranslatableMarkup('Regenerate Sitemap'),
  description: new TranslatableMarkup('Regenerate sitemap(s). Can regenerate all sitemaps or a specific variant. This is a write operation.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'variant' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sitemap Variant'),
      description: new TranslatableMarkup('Optional: specific sitemap variant to regenerate. If not provided, all variants will be regenerated.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'variant' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Regenerated Variant'),
      description: new TranslatableMarkup('The variant ID that was regenerated, or "all" if all variants were processed.'),
    ),
    'queue_status' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Queue Status'),
      description: new TranslatableMarkup('Queue information: items_queued, estimated_time. Generation happens via cron. Use GetStatus to check progress.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation. Note: regeneration is queued and completes during cron runs.'),
    ),
  ],
)]
class Regenerate extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'sitemap';
  protected const MCP_WRITE_KIND = 'ops';


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
    $variant = $input['variant'] ?? NULL;
    return $this->sitemapService->regenerate($variant);
  }

  

  

}
