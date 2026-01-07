<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\update\UpdateManagerInterface;
use Drupal\update\UpdateProcessorInterface;

/**
 * Service for checking security updates.
 */
class SecurityUpdateChecker {

  /**
   * Update status constants from update module.
   */
  protected const STATUS_LABELS = [
    1 => 'not_secure',           // UPDATE_NOT_SECURE
    2 => 'revoked',              // UPDATE_REVOKED
    3 => 'not_supported',        // UPDATE_NOT_SUPPORTED
    4 => 'not_current',          // UPDATE_NOT_CURRENT
    5 => 'current',              // UPDATE_CURRENT
    -1 => 'not_checked',         // UPDATE_NOT_CHECKED
    -2 => 'unknown',             // UPDATE_UNKNOWN
    -3 => 'not_fetched',         // UPDATE_NOT_FETCHED
    -4 => 'fetch_pending',       // UPDATE_FETCH_PENDING
  ];

  public function __construct(
    protected UpdateManagerInterface $updateManager,
    protected UpdateProcessorInterface $updateProcessor,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Get all available updates.
   *
   * @param bool $securityOnly
   *   If TRUE, only return security updates.
   *
   * @return array
   *   Array of available updates.
   */
  public function getAvailableUpdates(bool $securityOnly = FALSE): array {
    // Refresh update data if needed.
    if ($available = update_get_available(TRUE)) {
      $projectData = update_calculate_project_data($available);
    }
    else {
      return [
        'error' => 'Unable to fetch update information. Check update module configuration.',
        'updates' => [],
      ];
    }

    $updates = [];
    foreach ($projectData as $name => $project) {
      $status = $project['status'] ?? -2;
      $statusLabel = self::STATUS_LABELS[$status] ?? 'unknown';

      // Skip if current and we're looking for updates.
      if ($status === 5) {
        continue;
      }

      $isSecurity = in_array($status, [1, 2, 3]); // not_secure, revoked, not_supported

      // Filter to security only if requested.
      if ($securityOnly && !$isSecurity) {
        continue;
      }

      $updates[] = [
        'name' => $name,
        'label' => $project['info']['name'] ?? $name,
        'current_version' => $project['existing_version'] ?? 'Unknown',
        'recommended_version' => $project['recommended'] ?? NULL,
        'latest_version' => $project['latest_version'] ?? NULL,
        'status' => $statusLabel,
        'is_security_update' => $isSecurity,
        'project_type' => $project['project_type'] ?? 'module',
        'link' => $project['link'] ?? NULL,
      ];
    }

    // Sort: security updates first, then by name.
    usort($updates, function ($a, $b) {
      if ($a['is_security_update'] !== $b['is_security_update']) {
        return $b['is_security_update'] ? 1 : -1;
      }
      return strcmp($a['name'], $b['name']);
    });

    $securityCount = count(array_filter($updates, fn($u) => $u['is_security_update']));

    return [
      'total_updates' => count($updates),
      'security_updates' => $securityCount,
      'has_security_issues' => $securityCount > 0,
      'updates' => $updates,
    ];
  }

  /**
   * Get security updates only.
   *
   * @return array
   *   Array of security updates.
   */
  public function getSecurityUpdates(): array {
    return $this->getAvailableUpdates(TRUE);
  }

  /**
   * Check Drupal core status specifically.
   *
   * @return array
   *   Core update status.
   */
  public function getCoreStatus(): array {
    $available = update_get_available(TRUE);
    if (!$available || !isset($available['drupal'])) {
      return [
        'error' => 'Unable to fetch Drupal core update information.',
      ];
    }

    $projectData = update_calculate_project_data($available);
    $core = $projectData['drupal'] ?? NULL;

    if (!$core) {
      return [
        'error' => 'Drupal core project data not available.',
      ];
    }

    $status = $core['status'] ?? -2;
    $isSecurity = in_array($status, [1, 2, 3]);

    return [
      'current_version' => \Drupal::VERSION,
      'recommended_version' => $core['recommended'] ?? NULL,
      'latest_version' => $core['latest_version'] ?? NULL,
      'status' => self::STATUS_LABELS[$status] ?? 'unknown',
      'is_security_update' => $isSecurity,
      'security_updates_available' => !empty($core['security updates']),
      'release_link' => $core['releases'][$core['recommended']]['release_link'] ?? NULL,
    ];
  }

}
