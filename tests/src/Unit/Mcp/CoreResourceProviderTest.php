<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Resource\CoreResourceProvider;
use Drupal\mcp_tools\Service\ConfigAnalysisService;
use Drupal\mcp_tools\Service\SiteBlueprintService;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\mcp_tools\Service\SystemStatusService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CoreResourceProvider.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\Resource\CoreResourceProvider::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class CoreResourceProviderTest extends TestCase {

  private SiteHealthService $siteHealthService;
  private SystemStatusService $systemStatusService;
  private SiteBlueprintService $siteBlueprintService;
  private ConfigAnalysisService $configAnalysisService;
  private CoreResourceProvider $provider;

  protected function setUp(): void {
    parent::setUp();

    $this->siteHealthService = $this->createMock(SiteHealthService::class);
    $this->systemStatusService = $this->createMock(SystemStatusService::class);
    $this->siteBlueprintService = $this->createMock(SiteBlueprintService::class);
    $this->configAnalysisService = $this->createMock(ConfigAnalysisService::class);

    $this->provider = new CoreResourceProvider(
      $this->siteHealthService,
      $this->systemStatusService,
      $this->siteBlueprintService,
      $this->configAnalysisService,
    );
  }

  public function testGetResourcesReturnsExpectedResources(): void {
    $resources = $this->provider->getResources();

    $this->assertIsArray($resources);
    $this->assertCount(3, $resources);

    $uris = array_column($resources, 'uri');
    $this->assertContains('drupal://site/status', $uris);
    $this->assertContains('drupal://site/snapshot', $uris);
    $this->assertContains('drupal://system/requirements', $uris);
  }

  public function testResourcesHaveRequiredProperties(): void {
    $resources = $this->provider->getResources();

    foreach ($resources as $resource) {
      $this->assertArrayHasKey('uri', $resource);
      $this->assertArrayHasKey('name', $resource);
      $this->assertArrayHasKey('description', $resource);
      $this->assertArrayHasKey('mimeType', $resource);
      $this->assertArrayHasKey('handler', $resource);
      $this->assertSame('application/json', $resource['mimeType']);
      $this->assertIsCallable($resource['handler']);
    }
  }

  public function testGetResourceTemplatesReturnsEmptyArray(): void {
    $templates = $this->provider->getResourceTemplates();

    $this->assertSame([], $templates);
  }

  public function testGetSiteStatusReturnsHealthData(): void {
    $expected = [
      'site_name' => 'Test Site',
      'drupal_version' => '10.3.0',
      'php_version' => '8.3.0',
      'modules' => ['enabled' => 50],
    ];
    $this->siteHealthService->method('getSiteStatus')->willReturn($expected);

    $result = $this->provider->getSiteStatus();

    $this->assertSame($expected, $result);
  }

  public function testGetSiteSnapshotReturnsCompactOverview(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([
      'site_name' => 'Test Site',
      'site_uuid' => 'test-uuid-123',
      'drupal_version' => '10.3.0',
      'php_version' => '8.3.0',
      'install_profile' => 'standard',
      'maintenance_mode' => FALSE,
      'database' => ['type' => 'mysql'],
      'modules' => ['enabled' => 50],
      'cron' => ['last_run' => 1234567890],
    ]);

    $this->systemStatusService->method('getRequirements')
      ->with(TRUE)
      ->willReturn([
        'summary' => ['errors' => 0, 'warnings' => 1],
        'has_errors' => FALSE,
        'has_warnings' => TRUE,
        'total_checks' => 25,
      ]);

    $this->siteBlueprintService->method('getBlueprint')->willReturn([
      'content_types' => ['article', 'page'],
    ]);

    $this->configAnalysisService->method('getConfigStatus')->willReturn([
      'has_changes' => TRUE,
      'changes' => [
        ['name' => 'system.site', 'operation' => 'update'],
      ],
      'sync_directory_exists' => TRUE,
    ]);

    $result = $this->provider->getSiteSnapshot();

    $this->assertArrayHasKey('site', $result);
    $this->assertSame('Test Site', $result['site']['name']);
    $this->assertSame('test-uuid-123', $result['site']['uuid']);
    $this->assertSame('10.3.0', $result['site']['drupal_version']);
    $this->assertFalse($result['site']['maintenance_mode']);

    $this->assertArrayHasKey('database', $result);
    $this->assertArrayHasKey('modules', $result);
    $this->assertArrayHasKey('cron', $result);
    $this->assertArrayHasKey('blueprint', $result);

    $this->assertArrayHasKey('requirements', $result);
    $this->assertFalse($result['requirements']['has_errors']);
    $this->assertTrue($result['requirements']['has_warnings']);

    $this->assertArrayHasKey('config_drift', $result);
    $this->assertTrue($result['config_drift']['has_changes']);
  }

  public function testGetSiteSnapshotHandlesConfigError(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([]);
    $this->systemStatusService->method('getRequirements')->willReturn([]);
    $this->siteBlueprintService->method('getBlueprint')->willReturn([]);
    $this->configAnalysisService->method('getConfigStatus')->willReturn([
      'error' => 'Sync directory not configured',
    ]);

    $result = $this->provider->getSiteSnapshot();

    $this->assertFalse($result['config_drift']['has_changes']);
    $this->assertSame('Sync directory not configured', $result['config_drift']['error']);
  }

  public function testGetSiteSnapshotTruncatesLargeChangeLists(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([]);
    $this->systemStatusService->method('getRequirements')->willReturn([]);
    $this->siteBlueprintService->method('getBlueprint')->willReturn([]);

    // Create 30 changes.
    $changes = [];
    for ($i = 0; $i < 30; $i++) {
      $changes[] = ['name' => "config.$i", 'operation' => 'update'];
    }

    $this->configAnalysisService->method('getConfigStatus')->willReturn([
      'has_changes' => TRUE,
      'changes' => $changes,
      'sync_directory_exists' => TRUE,
    ]);

    $result = $this->provider->getSiteSnapshot();

    $this->assertSame(30, $result['config_drift']['total_changes']);
    $this->assertCount(20, $result['config_drift']['sample']);
    $this->assertTrue($result['config_drift']['sample_truncated']);
  }

  public function testGetSystemRequirementsReturnsRequirements(): void {
    $expected = [
      'requirements' => [
        ['title' => 'PHP', 'severity' => 0],
        ['title' => 'MySQL', 'severity' => 1],
      ],
      'summary' => ['errors' => 0, 'warnings' => 1],
    ];
    $this->systemStatusService->method('getRequirements')->willReturn($expected);

    $result = $this->provider->getSystemRequirements();

    $this->assertSame($expected, $result);
  }

  public function testResourceHandlersAreCallable(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([]);
    $this->systemStatusService->method('getRequirements')->willReturn([]);
    $this->siteBlueprintService->method('getBlueprint')->willReturn([]);
    $this->configAnalysisService->method('getConfigStatus')->willReturn([]);

    $resources = $this->provider->getResources();

    foreach ($resources as $resource) {
      $handler = $resource['handler'];
      $result = $handler();
      $this->assertIsArray($result);
    }
  }

}
