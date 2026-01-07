<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for gathering site health information.
 */
class SiteHealthService {

  public function __construct(
    protected ModuleExtensionList $moduleExtensionList,
    protected StateInterface $state,
    protected Connection $database,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Get comprehensive site status information.
   *
   * @return array
   *   Array containing site status data.
   */
  public function getSiteStatus(): array {
    return [
      'drupal_version' => \Drupal::VERSION,
      'php_version' => PHP_VERSION,
      'database' => $this->getDatabaseInfo(),
      'site_name' => $this->configFactory->get('system.site')->get('name'),
      'site_uuid' => $this->configFactory->get('system.site')->get('uuid'),
      'install_profile' => $this->configFactory->get('core.extension')->get('profile'),
      'modules' => $this->getModuleSummary(),
      'cron' => $this->getCronStatus(),
      'maintenance_mode' => $this->state->get('system.maintenance_mode', FALSE),
    ];
  }

  /**
   * Get database information.
   *
   * @return array
   *   Database driver and version info.
   */
  protected function getDatabaseInfo(): array {
    $connection = $this->database;
    return [
      'driver' => $connection->driver(),
      'version' => $connection->version(),
    ];
  }

  /**
   * Get summary of installed modules.
   *
   * @return array
   *   Module counts and lists.
   */
  protected function getModuleSummary(): array {
    $modules = $this->moduleExtensionList->getAllInstalledInfo();
    $enabled = array_filter($modules, fn($m) => $m['status'] ?? FALSE);

    return [
      'total_installed' => count($enabled),
      'core_count' => count(array_filter($enabled, fn($m) => ($m['package'] ?? '') === 'Core')),
      'contrib_count' => count(array_filter($enabled, fn($m) =>
        ($m['package'] ?? '') !== 'Core' && !str_starts_with($m['package'] ?? '', 'Custom')
      )),
      'custom_count' => count(array_filter($enabled, fn($m) =>
        str_starts_with($m['package'] ?? '', 'Custom')
      )),
    ];
  }

  /**
   * Get cron status information.
   *
   * @return array
   *   Cron timing and status.
   */
  public function getCronStatus(): array {
    $lastRun = $this->state->get('system.cron_last', 0);
    $lastRunFormatted = $lastRun
      ? date('Y-m-d H:i:s', $lastRun)
      : 'Never';

    $timeSinceLastRun = $lastRun
      ? time() - $lastRun
      : NULL;

    return [
      'last_run' => $lastRunFormatted,
      'last_run_timestamp' => $lastRun,
      'seconds_since_last_run' => $timeSinceLastRun,
      'status' => $this->evaluateCronHealth($timeSinceLastRun),
    ];
  }

  /**
   * Evaluate cron health based on time since last run.
   *
   * @param int|null $seconds
   *   Seconds since last cron run.
   *
   * @return string
   *   Health status: 'healthy', 'warning', 'critical', or 'unknown'.
   */
  protected function evaluateCronHealth(?int $seconds): string {
    if ($seconds === NULL) {
      return 'unknown';
    }
    // Warning if over 3 hours, critical if over 24 hours.
    if ($seconds > 86400) {
      return 'critical';
    }
    if ($seconds > 10800) {
      return 'warning';
    }
    return 'healthy';
  }

  /**
   * Get list of installed modules with details.
   *
   * @param bool $includeCore
   *   Whether to include core modules.
   *
   * @return array
   *   List of modules with version and package info.
   */
  public function getInstalledModules(bool $includeCore = FALSE): array {
    $modules = $this->moduleExtensionList->getAllInstalledInfo();
    $result = [];

    foreach ($modules as $name => $info) {
      if (!$includeCore && ($info['package'] ?? '') === 'Core') {
        continue;
      }

      $result[] = [
        'name' => $name,
        'label' => $info['name'] ?? $name,
        'version' => $info['version'] ?? 'Unknown',
        'package' => $info['package'] ?? 'Other',
        'description' => $info['description'] ?? '',
      ];
    }

    usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $result;
  }

}
