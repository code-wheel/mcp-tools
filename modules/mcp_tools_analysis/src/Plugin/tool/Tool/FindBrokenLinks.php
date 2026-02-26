<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\tool\Tool;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\mcp_tools\Service\RateLimiter;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
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
  id: 'mcp_analysis_broken_links',
  label: new TranslatableMarkup('Find Broken Links'),
  description: new TranslatableMarkup('Scan content for broken internal links (404s). Checks href attributes in text fields.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of links to check (1-500, default: 100)'),
      required: FALSE,
    ),
    'base_url' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base URL'),
      description: new TranslatableMarkup('Optional base URL override for STDIO/CLI usage (e.g., "https://example.com"). When omitted, uses the current request host.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'broken_links' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Broken Links'),
      description: new TranslatableMarkup('List of broken links found with source information'),
    ),
    'total_checked' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Checked'),
      description: new TranslatableMarkup('Number of links checked'),
    ),
    'broken_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Broken Count'),
      description: new TranslatableMarkup('Number of broken links found'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Recommendations for fixing issues'),
    ),
  ],
)]
class FindBrokenLinks extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'analysis';


  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * Read operation rate limiter.
   */
  protected RateLimiter $rateLimiter;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    $instance->rateLimiter = $container->get('mcp_tools.rate_limiter');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $limit = isset($input['limit']) ? (int) $input['limit'] : 100;
    $baseUrl = isset($input['base_url']) ? (string) $input['base_url'] : NULL;

    if ($limit < 1 || $limit > 500) {
      return ['success' => FALSE, 'error' => 'Limit must be between 1 and 500.'];
    }

    $settings = $this->configFactory->get('mcp_tools.settings');
    $maxUrlsPerScan = (int) ($settings->get('rate_limits.broken_link_scan.max_urls_per_scan') ?? 500);
    if ($maxUrlsPerScan > 0) {
      $limit = min($limit, $maxUrlsPerScan);
    }

    $rateCheck = $this->rateLimiter->checkReadLimit('broken_link_scan');
    if (!$rateCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $rateCheck['error'],
        'code' => $rateCheck['code'] ?? 'RATE_LIMIT_EXCEEDED',
        'retry_after' => $rateCheck['retry_after'] ?? NULL,
      ];
    }

    return $this->analysisService->findBrokenLinks($limit, $baseUrl);
  }

}
