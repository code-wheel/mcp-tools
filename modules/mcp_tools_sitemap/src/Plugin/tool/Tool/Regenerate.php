<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\tool\Tool;

use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
  description: new TranslatableMarkup('Regenerate sitemap(s). Can regenerate all sitemaps or a specific variant.'),
  operation: ToolOperation::Write,
  destructive: FALSE,
  input_definitions: [
    'variant' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Sitemap Variant'),
      description: new TranslatableMarkup('Optional: specific sitemap variant to regenerate. If not provided, all variants will be regenerated.'),
      required: FALSE,
    ),
    'continue' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Continue'),
      description: new TranslatableMarkup('true to go on where the last call stopped. A big site takes several calls: each generates for a few seconds and says how much is left.'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'variant' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Regenerated Variant'),
      description: new TranslatableMarkup('The variant ID that was regenerated, or "all" if all variants were processed.'),
    ),
    'queue_status' => new OutputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Queue Status'),
      description: new TranslatableMarkup('Queue information: total and processed. Use mcp_sitemap_status to check progress.'),
    ),
    'remaining' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Remaining'),
      description: new TranslatableMarkup('Elements still to generate. Call again with continue: true while it is above 0.'),
    ),
    'done' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Done'),
      description: new TranslatableMarkup('Whether the sitemap is generated in full.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable result: generated, or how much is left.'),
    ),
  ],
)]
class Regenerate extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'sitemap';
  protected const MCP_WRITE_KIND = 'ops';


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
    $variant = $input['variant'] ?? NULL;
    $continue = (bool) ($input['continue'] ?? FALSE);
    return $this->sitemapService->regenerate($variant, $continue);
  }

}
