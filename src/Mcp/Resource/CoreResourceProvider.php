<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Mcp\FiberIsolation;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\mcp_tools\Service\SiteBlueprintService;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\mcp_tools\Service\SystemStatusService;

/**
 * Core MCP resource provider for site context.
 */
class CoreResourceProvider implements ResourceProviderInterface {

  public function __construct(
    private readonly SiteHealthService $siteHealthService,
    private readonly SystemStatusService $systemStatusService,
    private readonly SiteBlueprintService $siteBlueprintService,
    private readonly ConfigAnalysisService $configAnalysisService,
    private readonly ?EntityTypeManagerInterface $entityTypeManager = NULL,
    private readonly ?AccessManager $accessManager = NULL,
    private readonly ?AccountInterface $currentUser = NULL,
    private readonly ?McpToolCallContext $toolCallContext = NULL,
  ) {}

  /**
   * Runs a read the way a tool call runs: starting like a new request.
   *
   * In the long-running STDIO server, what one read loaded stays in this
   * process's memory. Without this, a change another process made did not
   * show until some tool call happened to reset it.
   */
  private function fresh(callable $read): mixed {
    $this->toolCallContext?->enter();
    try {
      return $read();
    }
    finally {
      $this->toolCallContext?->leave();
    }
  }

  /**
   * Whether the current user may read what a tool category covers.
   *
   * A resource shows the same data as the tools of its category, so it
   * answers to the same rule: the read scope and the category's permission.
   * Without the services to check, the answer is no.
   */
  private function mayRead(string $category): bool {
    if ($this->accessManager === NULL || $this->currentUser === NULL) {
      return FALSE;
    }
    return $this->accessManager->canRead()
      && $this->currentUser->hasPermission('mcp_tools use ' . $category);
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => 'drupal://site/status',
        'name' => 'site-status',
        'description' => 'Basic site health summary, versions, cron, and module counts.',
        'mimeType' => 'application/json',
        'handler' => fn() => FiberIsolation::run(fn() => $this->fresh(fn() => $this->getSiteStatus())),
      ],
      [
        'uri' => 'drupal://site/snapshot',
        'name' => 'site-snapshot',
        'description' => 'Concise site overview for MCP context windows.',
        'mimeType' => 'application/json',
        'handler' => fn() => FiberIsolation::run(fn() => $this->fresh(fn() => $this->getSiteSnapshot())),
      ],
      [
        'uri' => 'drupal://system/requirements',
        'name' => 'system-requirements',
        'description' => 'Runtime requirements report from all installed modules.',
        'mimeType' => 'application/json',
        'handler' => fn() => FiberIsolation::run(fn() => $this->fresh(fn() => $this->getSystemRequirements())),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceTemplates(): array {
    return [
      [
        'uriTemplate' => 'drupal://node/{nid}',
        'name' => 'node-summary',
        'description' => 'Read-only summary of a node: metadata, publication status, languages, and text field values.',
        'mimeType' => 'application/json',
        'handler' => fn(string $nid): array => FiberIsolation::run(fn() => $this->fresh(fn() => $this->readNode($nid))),
      ],
      [
        'uriTemplate' => 'drupal://config/{name}',
        'name' => 'config-object',
        'description' => 'A configuration object by name. Sensitive keys are redacted unless output.include_sensitive is enabled.',
        'mimeType' => 'application/json',
        'handler' => fn(string $name): array => FiberIsolation::run(fn() => $this->fresh(fn() => $this->readConfig($name))),
      ],
    ];
  }

  /**
   * Resource handler for drupal://node/{nid}.
   *
   * @param string $nid
   *   The node ID from the URI.
   *
   * @return array<string, mixed>
   *   Node summary, or an error entry.
   */
  public function readNode(string $nid): array {
    if (!$this->mayRead('content')) {
      return ['error' => 'Access denied.'];
    }
    if ($this->entityTypeManager === NULL) {
      return ['error' => 'Entity type manager unavailable.'];
    }
    $node = $this->entityTypeManager->getStorage('node')->load((int) $nid);
    if ($node === NULL) {
      return ['error' => "Node $nid not found."];
    }
    if (!$node->access('view')) {
      return ['error' => 'Access denied.'];
    }

    $textTypes = ['string', 'string_long', 'text', 'text_long', 'text_with_summary'];
    $fields = [];
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if (!str_starts_with($name, 'field_') && $name !== 'body') {
        continue;
      }
      if (!in_array($definition->getType(), $textTypes, TRUE) || $node->get($name)->isEmpty()) {
        continue;
      }
      $fields[$name] = $node->get($name)->value;
    }

    return [
      'nid' => (int) $node->id(),
      'uuid' => $node->uuid(),
      'type' => $node->bundle(),
      'title' => $node->label(),
      'status' => $node->isPublished() ? 'published' : 'unpublished',
      'langcode' => $node->language()->getId(),
      'languages' => array_keys($node->getTranslationLanguages()),
      'created' => (int) $node->getCreatedTime(),
      'changed' => (int) $node->getChangedTime(),
      'fields' => $fields,
    ];
  }

  /**
   * Resource handler for drupal://config/{name}.
   *
   * @param string $name
   *   The configuration object name from the URI.
   *
   * @return array<string, mixed>
   *   The configuration data with sensitive keys redacted by default.
   */
  public function readConfig(string $name): array {
    if (!$this->mayRead('config')) {
      return ['error' => 'Access denied.'];
    }
    return $this->configAnalysisService->getConfig($name);
  }

  /**
   * Resource handler for site status.
   *
   * @return array<string, mixed>
   *   Site status summary.
   */
  public function getSiteStatus(): array {
    if (!$this->mayRead('site_health')) {
      return ['error' => 'Access denied.'];
    }
    return $this->siteHealthService->getSiteStatus();
  }

  /**
   * Resource handler for a compact site snapshot.
   *
   * @return array<string, mixed>
   *   Site snapshot.
   */
  public function getSiteSnapshot(): array {
    if (!$this->mayRead('site_health')) {
      return ['error' => 'Access denied.'];
    }
    $status = $this->siteHealthService->getSiteStatus();
    $requirements = $this->systemStatusService->getRequirements(TRUE);

    // The blueprint and the config changes show what the structure, users
    // and config tools show, so they answer to those permissions. The site
    // health permission alone gets the site health part.
    $blueprint = ['note' => 'Needs the permission "mcp_tools use structure".'];
    if ($this->mayRead('structure')) {
      $blueprint = $this->siteBlueprintService->getBlueprint();
      if (!$this->mayRead('users')) {
        unset($blueprint['roles']);
      }
    }
    $configDrift = ['note' => 'Needs the permission "mcp_tools use config".'];
    if ($this->mayRead('config')) {
      $configDrift = $this->summarizeConfigDrift($this->configAnalysisService->getConfigStatus());
    }

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
      'config_drift' => $configDrift,
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
    if (!$this->mayRead('site_health')) {
      return ['error' => 'Access denied.'];
    }
    return $this->systemStatusService->getRequirements();
  }

}
