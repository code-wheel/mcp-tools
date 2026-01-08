<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Resource;

use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\mcp_tools\Service\SiteBlueprintService;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\mcp_tools\Service\SystemStatusService;

/**
 * Core MCP resource provider for site context.
 */
final class CoreResourceProvider implements ResourceProviderInterface {

  public function __construct(
    private readonly SiteHealthService $siteHealthService,
    private readonly SystemStatusService $systemStatusService,
    private readonly SiteBlueprintService $siteBlueprintService,
    private readonly ConfigAnalysisService $configAnalysisService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => 'drupal://site/status',
        'name' => 'Site Status',
        'description' => 'Basic site health summary, versions, cron, and module counts.',
        'mimeType' => 'application/json',
        'handler' => [$this, 'getSiteStatus'],
      ],
      [
        'uri' => 'drupal://site/snapshot',
        'name' => 'Site Snapshot',
        'description' => 'Concise site overview for MCP context windows.',
        'mimeType' => 'application/json',
        'handler' => [$this, 'getSiteSnapshot'],
      ],
      [
        'uri' => 'drupal://system/requirements',
        'name' => 'System Requirements',
        'description' => 'Runtime requirements report from all installed modules.',
        'mimeType' => 'application/json',
        'handler' => [$this, 'getSystemRequirements'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceTemplates(): array {
    return [];
  }

  /**
   * Resource handler for site status.
   *
   * @return array<string, mixed>
   *   Site status summary.
   */
  public function getSiteStatus(): array {
    return $this->siteHealthService->getSiteStatus();
  }

  /**
   * Resource handler for a compact site snapshot.
   *
   * @return array<string, mixed>
   *   Site snapshot.
   */
  public function getSiteSnapshot(): array {
    $status = $this->siteHealthService->getSiteStatus();
    $requirements = $this->systemStatusService->getRequirements(TRUE);
    $blueprint = $this->siteBlueprintService->getBlueprint();
    $configStatus = $this->configAnalysisService->getConfigStatus();

    return [
      'site' => [
        'name' => $status['site_name'] ?? '',
        'uuid' => $status['site_uuid'] ?? '',
        'drupal_version' => $status['drupal_version'] ?? '',
        'php_version' => $status['php_version'] ?? '',
        'install_profile' => $status['install_profile'] ?? '',
        'maintenance_mode' => (bool) ($status['maintenance_mode'] ?? FALSE),
      ],
      'database' => $status['database'] ?? [],
      'modules' => $status['modules'] ?? [],
      'cron' => $status['cron'] ?? [],
      'blueprint' => $blueprint,
      'requirements' => [
        'summary' => $requirements['summary'] ?? [],
        'has_errors' => (bool) ($requirements['has_errors'] ?? FALSE),
        'has_warnings' => (bool) ($requirements['has_warnings'] ?? FALSE),
        'total_checks' => (int) ($requirements['total_checks'] ?? 0),
      ],
      'config_drift' => $this->summarizeConfigDrift($configStatus),
    ];
  }

  /**
   * Build a compact config drift summary.
   *
   * @param array<string, mixed> $status
   *   Config status result.
   *
   * @return array<string, mixed>
   *   Drift summary.
   */
  private function summarizeConfigDrift(array $status): array {
    if (isset($status['error'])) {
      return [
        'has_changes' => FALSE,
        'error' => $status['error'],
      ];
    }

    $changes = $status['changes'] ?? [];
    $summary = [
      'create' => 0,
      'update' => 0,
      'delete' => 0,
      'rename' => 0,
    ];

    foreach ($changes as $change) {
      $op = $change['operation'] ?? '';
      if (isset($summary[$op])) {
        $summary[$op]++;
      }
    }

    $sample = array_slice($changes, 0, 20);

    return [
      'has_changes' => (bool) ($status['has_changes'] ?? FALSE),
      'total_changes' => count($changes),
      'changes_by_operation' => $summary,
      'sample' => $sample,
      'sample_truncated' => count($changes) > count($sample),
      'sync_directory_exists' => (bool) ($status['sync_directory_exists'] ?? FALSE),
    ];
  }

  /**
   * Resource handler for system requirements.
   *
   * @return array<string, mixed>
   *   Requirements report.
   */
  public function getSystemRequirements(): array {
    return $this->systemStatusService->getRequirements();
  }

}
