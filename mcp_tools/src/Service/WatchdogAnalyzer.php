<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;

/**
 * Service for analyzing watchdog/dblog entries.
 */
class WatchdogAnalyzer {

  /**
   * Severity level constants matching Drupal's RfcLogLevel.
   */
  protected const SEVERITY_LABELS = [
    0 => 'emergency',
    1 => 'alert',
    2 => 'critical',
    3 => 'error',
    4 => 'warning',
    5 => 'notice',
    6 => 'info',
    7 => 'debug',
  ];

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Get recent log entries.
   *
   * @param int $limit
   *   Maximum entries to return.
   * @param array $severities
   *   Filter by severity levels (0-7). Empty = all.
   * @param string|null $type
   *   Filter by log type (e.g., 'php', 'cron', 'user').
   *
   * @return array
   *   Array of log entries.
   */
  public function getRecentEntries(int $limit = 50, array $severities = [], ?string $type = NULL): array {
    if (!$this->database->schema()->tableExists('watchdog')) {
      return [
        'error' => 'Database logging (dblog) is not enabled.',
        'entries' => [],
      ];
    }

    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'type', 'severity', 'message', 'variables', 'timestamp'])
      ->orderBy('wid', 'DESC')
      ->range(0, $limit);

    if (!empty($severities)) {
      $query->condition('severity', $severities, 'IN');
    }

    if ($type !== NULL) {
      $query->condition('type', $type);
    }

    $results = $query->execute()->fetchAll();
    $entries = [];

    foreach ($results as $row) {
      $entries[] = [
        'id' => $row->wid,
        'type' => $row->type,
        'severity' => self::SEVERITY_LABELS[$row->severity] ?? 'unknown',
        'severity_level' => (int) $row->severity,
        'message' => $this->formatMessage($row->message, $row->variables),
        'timestamp' => date('Y-m-d H:i:s', $row->timestamp),
      ];
    }

    return [
      'total_returned' => count($entries),
      'entries' => $entries,
    ];
  }

  /**
   * Get error summary grouped by type.
   *
   * @param int $hours
   *   Look back this many hours.
   *
   * @return array
   *   Summary of errors grouped by type.
   */
  public function getErrorSummary(int $hours = 24): array {
    if (!$this->database->schema()->tableExists('watchdog')) {
      return [
        'error' => 'Database logging (dblog) is not enabled.',
        'summary' => [],
      ];
    }

    $since = time() - ($hours * 3600);

    // Get counts by type and severity for errors/warnings/criticals.
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['type', 'severity'])
      ->condition('severity', 4, '<=') // warning and above
      ->condition('timestamp', $since, '>=');

    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('w.type');
    $query->groupBy('w.severity');
    $query->orderBy('count', 'DESC');

    $results = $query->execute()->fetchAll();

    $summary = [];
    foreach ($results as $row) {
      $type = $row->type;
      if (!isset($summary[$type])) {
        $summary[$type] = [
          'type' => $type,
          'total' => 0,
          'by_severity' => [],
        ];
      }
      $severityLabel = self::SEVERITY_LABELS[$row->severity] ?? 'unknown';
      $summary[$type]['by_severity'][$severityLabel] = (int) $row->count;
      $summary[$type]['total'] += (int) $row->count;
    }

    // Sort by total descending.
    usort($summary, fn($a, $b) => $b['total'] - $a['total']);

    return [
      'period_hours' => $hours,
      'since' => date('Y-m-d H:i:s', $since),
      'types' => array_values($summary),
      'total_issues' => array_sum(array_column($summary, 'total')),
    ];
  }

  /**
   * Get the most recent errors (severity <= error).
   *
   * @param int $limit
   *   Maximum entries.
   *
   * @return array
   *   Recent error entries.
   */
  public function getRecentErrors(int $limit = 20): array {
    // Severity 0-3 = emergency, alert, critical, error.
    return $this->getRecentEntries($limit, [0, 1, 2, 3]);
  }

  /**
   * Format a log message with variable substitution.
   *
   * @param string $message
   *   The message template.
   * @param string|null $variables
   *   Serialized variables.
   *
   * @return string
   *   Formatted message.
   */
  protected function formatMessage(string $message, ?string $variables): string {
    if (empty($variables)) {
      return $message;
    }

    try {
      $vars = @unserialize($variables, ['allowed_classes' => FALSE]);
      if (is_array($vars)) {
        // Strip HTML tags from variables for clean output.
        $cleanVars = array_map(fn($v) => is_string($v) ? strip_tags($v) : $v, $vars);
        return strtr($message, $cleanVars);
      }
    }
    catch (\Exception) {
      // Return raw message if unserialization fails.
    }

    return $message;
  }

}
