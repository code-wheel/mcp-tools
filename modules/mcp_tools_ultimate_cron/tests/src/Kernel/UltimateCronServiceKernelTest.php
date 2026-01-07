<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;

/**
 * Kernel tests for UltimateCronService.
 *
 * @group mcp_tools_ultimate_cron
 * @requires module ultimate_cron
 */
final class UltimateCronServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ultimate_cron',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_ultimate_cron',
  ];

  /**
   * The ultimate cron service under test.
   */
  private UltimateCronService $ultimateCronService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('ultimate_cron_job');

    $this->ultimateCronService = $this->container->get('mcp_tools_ultimate_cron.ultimate_cron');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing jobs.
   */
  public function testListJobs(): void {
    $result = $this->ultimateCronService->listJobs();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('jobs', $result['data']);
  }

  /**
   * Test getting non-existent job.
   */
  public function testGetJobNotFound(): void {
    $result = $this->ultimateCronService->getJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test running non-existent job.
   */
  public function testRunJobNotFound(): void {
    $result = $this->ultimateCronService->runJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test enabling non-existent job.
   */
  public function testEnableJobNotFound(): void {
    $result = $this->ultimateCronService->enableJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test disabling non-existent job.
   */
  public function testDisableJobNotFound(): void {
    $result = $this->ultimateCronService->disableJob('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting logs for non-existent job.
   */
  public function testGetJobLogsNotFound(): void {
    $result = $this->ultimateCronService->getJobLogs('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->ultimateCronService->runJob('test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    $result = $this->ultimateCronService->enableJob('test');
    $this->assertFalse($result['success']);

    $result = $this->ultimateCronService->disableJob('test');
    $this->assertFalse($result['success']);
  }

}
