<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for gathering system status and requirements.
 */
class SystemStatusService {

  use StringTranslationTrait;

  /**
   * Severity level labels, by the number of the severity.
   *
   * Drupal 10 and 11 name the numbers SystemManager::REQUIREMENT_OK,
   * _WARNING and _ERROR, and a global REQUIREMENT_INFO. From 11.2 there is
   * the enum RequirementSeverity with the same numbers, and Drupal 12 has
   * only the enum. The numbers are what all of them share.
   */
  protected const SEVERITY_LABELS = [
    -1 => 'info',
    0 => 'ok',
    1 => 'warning',
    2 => 'error',
  ];

  /**
   * The number of the severity "warning".
   */
  private const WARNING = 1;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected Connection $database,
    protected ?RendererInterface $renderer = NULL,
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

    // Drupal 11.2 moved runtime checks to hook_runtime_requirements(), and
    // core's own checks went there: without this call the report has no
    // core entry at all on Drupal 11.2 and later. Older cores have no
    // implementations. Then the alter hooks, as the status report page runs
    // them (SystemManager::listRequirements()).
    $runtimeRequirements = $this->moduleHandler->invokeAll('runtime_requirements');
    if (is_array($runtimeRequirements)) {
      $requirements = array_merge($requirements, $runtimeRequirements);
    }
    $this->moduleHandler->alter('requirements', $requirements);
    $this->moduleHandler->alter('runtime_requirements', $requirements);

    $items = [];
    $summary = [
      'info' => 0,
      'ok' => 0,
      'warning' => 0,
      'error' => 0,
    ];

    foreach ($requirements as $key => $requirement) {
      $severity = $requirement['severity'] ?? -1;
      // Since Drupal 11.2 a requirement may give its severity as the
      // RequirementSeverity enum instead of an integer; Drupal 12 only has
      // the enum. Its values are the same integers.
      if ($severity instanceof \BackedEnum) {
        $severity = $severity->value;
      }
      $severityLabel = self::SEVERITY_LABELS[$severity] ?? 'unknown';

      $summary[$severityLabel] = ($summary[$severityLabel] ?? 0) + 1;

      // Skip non-errors if errorsOnly is true.
      if ($errorsOnly && $severity < self::WARNING) {
        continue;
      }

      $items[] = [
        'key' => $key,
        'title' => $this->plainText($requirement['title'] ?? NULL) ?? (string) $key,
        'value' => $this->plainText($requirement['value'] ?? NULL),
        'description' => $this->plainText($requirement['description'] ?? NULL),
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

  /**
   * Turns a requirement's title, value or description into plain text.
   *
   * Modules give these as strings, markup objects or render arrays (Drupal
   * 10's update status is one). Casting a render array to a string gave the
   * text "Array" and a PHP warning.
   */
  private function plainText(mixed $value): ?string {
    if ($value === NULL) {
      return NULL;
    }
    if (is_array($value)) {
      if ($this->renderer === NULL) {
        return NULL;
      }
      try {
        $value = $this->renderer->renderInIsolation($value);
      }
      catch (\Throwable) {
        return NULL;
      }
    }
    return trim(strip_tags((string) $value));
  }

}
