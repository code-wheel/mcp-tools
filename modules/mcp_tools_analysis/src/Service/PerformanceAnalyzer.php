<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service for analyzing site performance.
 */
class PerformanceAnalyzer {

  public function __construct(
    protected Connection $database,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Analyze site performance metrics.
   *
   * @return array
   *   Performance analysis results.
   */
  public function analyzePerformance(): array {
    try {
      $results = [
        'cache_status' => [],
        'slow_queries' => [],
        'watchdog_errors' => [],
        'render_times' => [],
      ];

      // Get cache settings.
      $systemPerformance = $this->configFactory->get('system.performance');
      $results['cache_status'] = [
        'page_cache_max_age' => $systemPerformance->get('cache.page.max_age'),
        'css_aggregation' => $systemPerformance->get('css.preprocess'),
        'js_aggregation' => $systemPerformance->get('js.preprocess'),
      ];

      // Analyze watchdog for performance issues (if dblog enabled).
      if ($this->moduleHandler->moduleExists('dblog')) {
        // Get recent PHP errors.
        $query = $this->database->select('watchdog', 'w')
          ->fields('w', ['message', 'variables', 'timestamp', 'type'])
          ->condition('type', ['php', 'system'], 'IN')
          ->condition('severity', [0, 1, 2, 3], 'IN')
          ->orderBy('timestamp', 'DESC')
          ->range(0, 20);
        $errorLogs = $query->execute()->fetchAll();

        foreach ($errorLogs as $log) {
          $results['watchdog_errors'][] = [
            'type' => $log->type,
            'message' => substr($log->message, 0, 200),
            'timestamp' => date('Y-m-d H:i:s', $log->timestamp),
          ];
        }

        // Look for slow page warnings.
        $slowQuery = $this->database->select('watchdog', 'w')
          ->fields('w', ['message', 'variables', 'timestamp'])
          ->condition('message', '%slow%', 'LIKE')
          ->orderBy('timestamp', 'DESC')
          ->range(0, 10);
        $slowLogs = $slowQuery->execute()->fetchAll();

        foreach ($slowLogs as $log) {
          $results['slow_queries'][] = [
            'message' => substr($log->message, 0, 200),
            'timestamp' => date('Y-m-d H:i:s', $log->timestamp),
          ];
        }
      }

      // Check database size.
      $dbSizeQuery = $this->database->query("
        SELECT table_name,
               ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
        LIMIT 10
      ");
      $largestTables = $dbSizeQuery->fetchAll();

      $results['database'] = [
        'largest_tables' => array_map(function ($row) {
          return [
            'table' => $row->table_name,
            'size_mb' => $row->size_mb,
          ];
        }, $largestTables),
      ];

      // Generate suggestions.
      $suggestions = [];

      if ($results['cache_status']['page_cache_max_age'] === 0) {
        $suggestions[] = 'Enable page caching for better performance (set max_age > 0).';
      }
      if (!$results['cache_status']['css_aggregation']) {
        $suggestions[] = 'Enable CSS aggregation to reduce HTTP requests.';
      }
      if (!$results['cache_status']['js_aggregation']) {
        $suggestions[] = 'Enable JavaScript aggregation to reduce HTTP requests.';
      }
      if (!empty($results['watchdog_errors'])) {
        $suggestions[] = 'Review and fix PHP errors in the watchdog log.';
      }
      if (!empty($results['slow_queries'])) {
        $suggestions[] = 'Investigate slow queries and consider adding database indexes.';
      }

      $suggestions[] = 'Consider using Redis or Memcache for cache backend.';
      $suggestions[] = 'Review Views queries and enable Views caching where appropriate.';

      return [
        'success' => TRUE,
        'data' => [
          'cache_status' => $results['cache_status'],
          'watchdog_errors' => $results['watchdog_errors'],
          'error_count' => count($results['watchdog_errors']),
          'slow_queries' => $results['slow_queries'],
          'database' => $results['database'],
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to analyze performance: ' . $e->getMessage()];
    }
  }

}
