<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\tool\Tool;

use Drupal\mcp_tools_analysis\Service\AnalysisService;
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
  id: 'mcp_analysis_performance',
  label: new TranslatableMarkup('Analyze Performance'),
  description: new TranslatableMarkup('Review cache settings, check watchdog for errors, and analyze database performance.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'cache_status' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Cache Status'),
      description: new TranslatableMarkup('Current cache configuration settings'),
    ),
    'watchdog_errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Watchdog Errors'),
      description: new TranslatableMarkup('Recent PHP and system errors from watchdog'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Total watchdog errors found. High counts indicate systemic issues.'),
    ),
    'slow_queries' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Slow Queries'),
      description: new TranslatableMarkup('Slow query warnings from logs'),
    ),
    'database' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Database Info'),
      description: new TranslatableMarkup('Database statistics including largest tables'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Actionable performance improvement recommendations.'),
    ),
  ],
)]
class AnalyzePerformance extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'analysis';


  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->analysisService->analyzePerformance();
  }

}
