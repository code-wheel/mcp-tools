<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for analyzing site performance.
 *
 * @Tool(
 *   id = "mcp_analysis_performance",
 *   label = @Translation("Analyze Performance"),
 *   description = @Translation("Review cache settings, check watchdog for errors, and analyze database performance."),
 *   category = "analysis",
 * )
 */
class AnalyzePerformance extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return $this->analysisService->analyzePerformance();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'cache_status' => [
        'type' => 'map',
        'label' => 'Cache Status',
        'description' => 'Current cache configuration settings',
      ],
      'watchdog_errors' => [
        'type' => 'array',
        'label' => 'Watchdog Errors',
        'description' => 'Recent PHP and system errors from watchdog',
      ],
      'error_count' => [
        'type' => 'integer',
        'label' => 'Error Count',
      ],
      'slow_queries' => [
        'type' => 'array',
        'label' => 'Slow Queries',
        'description' => 'Slow query warnings from logs',
      ],
      'database' => [
        'type' => 'map',
        'label' => 'Database Info',
        'description' => 'Database statistics including largest tables',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
