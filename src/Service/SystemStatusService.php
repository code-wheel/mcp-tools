<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\system\SystemManager;

/**
 * Service for gathering system status and requirements.
 */
class SystemStatusService {

  use StringTranslationTrait;

  /**
   * Severity level labels.
   */
  protected const SEVERITY_LABELS = [
    // REQUIREMENT_INFO is a global constant defined in
    // core/includes/install.inc. It is not available as a
    // SystemManager class constant in Drupal 10/11.
    -1 => 'info',
    SystemManager::REQUIREMENT_OK => 'ok',
    SystemManager::REQUIREMENT_WARNING => 'warning',
    SystemManager::REQUIREMENT_ERROR => 'error',
  ];

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected Connection $database,
  ) {}

  /**
   * Get system requirements/status report.
   *
   * @param bool $errorsOnly
   *   If TRUE, only return warnings and errors.
   *
   * @return array
   *   System requirements data.
   */
  public function getRequirements(bool $errorsOnly = FALSE): array {
    // Include runtime requirements.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $requirements = [];

    // Get requirements from all modules.
    foreach ($this->moduleHandler->getModuleList() as $module => $info) {
      $this->moduleHandler->loadInclude($module, 'install');
      $function = $module . '_requirements';
      if (function_exists($function)) {
        $moduleRequirements = $function('runtime');
        if (is_array($moduleRequirements)) {
          $requirements = array_merge($requirements, $moduleRequirements);
        }
      }
    }

    $items = [];
    $summary = [
      'info' => 0,
      'ok' => 0,
      'warning' => 0,
      'error' => 0,
    ];

    foreach ($requirements as $key => $requirement) {
      $severity = $requirement['severity'] ?? (defined('REQUIREMENT_INFO') ? REQUIREMENT_INFO : -1);
      $severityLabel = self::SEVERITY_LABELS[$severity] ?? 'unknown';

      $summary[$severityLabel] = ($summary[$severityLabel] ?? 0) + 1;

      // Skip non-errors if errorsOnly is true.
      if ($errorsOnly && $severity < SystemManager::REQUIREMENT_WARNING) {
        continue;
      }

      $items[] = [
        'key' => $key,
        'title' => (string) ($requirement['title'] ?? $key),
        'value' => isset($requirement['value']) ? strip_tags((string) $requirement['value']) : NULL,
        'description' => isset($requirement['description']) ? strip_tags((string) $requirement['description']) : NULL,
        'severity' => $severityLabel,
      ];
    }

    // Sort by severity (errors first).
    usort($items, function ($a, $b) {
      $order = ['error' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3];
      return ($order[$a['severity']] ?? 4) - ($order[$b['severity']] ?? 4);
    });

    return [
      'summary' => $summary,
      'has_errors' => $summary['error'] > 0,
      'has_warnings' => $summary['warning'] > 0,
      'total_checks' => array_sum($summary),
      'items' => $items,
    ];
  }

  /**
   * Get PHP information relevant to Drupal.
   *
   * @return array
   *   PHP configuration data.
   */
  public function getPhpInfo(): array {
    return [
      'version' => PHP_VERSION,
      'memory_limit' => ini_get('memory_limit'),
      'max_execution_time' => ini_get('max_execution_time'),
      'upload_max_filesize' => ini_get('upload_max_filesize'),
      'post_max_size' => ini_get('post_max_size'),
      'max_input_vars' => ini_get('max_input_vars'),
      'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== FALSE,
      'extensions' => get_loaded_extensions(),
    ];
  }

  /**
   * Get database status information.
   *
   * @return array
   *   Database status data.
   */
  public function getDatabaseStatus(): array {
    $info = [
      'driver' => $this->database->driver(),
      'version' => $this->database->version(),
      'database_name' => $this->database->getConnectionOptions()['database'] ?? 'unknown',
      'host' => $this->database->getConnectionOptions()['host'] ?? 'localhost',
      'prefix' => $this->database->getConnectionOptions()['prefix'] ?? '',
    ];

    // Get table count.
    try {
      $tables = $this->database->schema()->findTables('%');
      $info['table_count'] = count($tables);
    }
    catch (\Exception $e) {
      $info['table_count'] = 'unknown';
    }

    return $info;
  }

}
