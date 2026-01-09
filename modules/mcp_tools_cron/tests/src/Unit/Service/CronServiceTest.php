<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_cron\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\Config;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools_cron\Service\CronService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_cron\Service\CronService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_cron')]
final class CronServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): CronService {
    return new CronService(
      $overrides['cron'] ?? $this->createMock(CronInterface::class),
      $overrides['state'] ?? $this->createMock(StateInterface::class),
      $overrides['config_factory'] ?? $this->createMock(ConfigFactoryInterface::class),
      $overrides['module_handler'] ?? $this->createMock(ModuleHandlerInterface::class),
      $overrides['queue_worker_manager'] ?? $this->createMock(QueueWorkerManagerInterface::class),
      $overrides['queue_factory'] ?? $this->createMock(QueueFactory::class),
    );
  }

  public function testGetCronStatusIncludesJobsFromHooksAndQueueWorkers(): void {
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnMap([
      ['system.cron_last', 0, 1700000000],
      ['system.cron_key', '', 'cronkey'],
    ]);

    $cronConfig = $this->createMock(ImmutableConfig::class);
    $cronConfig->method('get')->with('threshold.autorun')->willReturn(3600);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('system.cron')->willReturn($cronConfig);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('invokeAllWith')->with(
      'cron',
      $this->isType('callable'),
    )->willReturnCallback(static function (string $hook, callable $callback): void {
      $callback(static fn() => NULL, 'system');
      $callback(static fn() => NULL, 'node');
    });

    $queueWorkerManager = $this->createMock(QueueWorkerManagerInterface::class);
    $queueWorkerManager->method('getDefinitions')->willReturn([
      'foo' => ['provider' => 'system', 'title' => 'Foo', 'cron' => ['time' => 30]],
      'bar' => ['provider' => 'system', 'title' => 'Bar'],
    ]);

    $service = $this->createService([
      'state' => $state,
      'config_factory' => $configFactory,
      'module_handler' => $moduleHandler,
      'queue_worker_manager' => $queueWorkerManager,
    ]);

    $status = $service->getCronStatus();
    $this->assertTrue($status['success']);
    $this->assertSame(1700000000, $status['data']['last_run_timestamp']);
    $this->assertSame(3600, $status['data']['autorun_threshold']);
    $this->assertSame('1 hours', $status['data']['threshold_human']);
    $this->assertSame('cronkey', $status['data']['cron_key']);
    $this->assertGreaterThanOrEqual(3, $status['data']['jobs_count']);
  }

  public function testRunCronReturnsSuccessWhenCronRuns(): void {
    $cron = $this->createMock(CronInterface::class);
    $cron->method('run')->willReturn(TRUE);

    $state = $this->createMock(StateInterface::class);
    $calls = 0;
    $state->method('get')->willReturnCallback(static function (string $key, mixed $default = NULL) use (&$calls): mixed {
      if ($key !== 'system.cron_last') {
        return $default;
      }
      $calls++;
      return $calls === 1 ? 0 : 1700000100;
    });

    $service = $this->createService([
      'cron' => $cron,
      'state' => $state,
    ]);

    $result = $service->runCron();
    $this->assertTrue($result['success']);
    $this->assertSame('Never', $result['previous_run']);
    $this->assertSame(date('Y-m-d H:i:s', 1700000100), $result['current_run']);
  }

  public function testRunQueueReturnsNotFoundForUnknownQueue(): void {
    $queueWorkerManager = $this->createMock(QueueWorkerManagerInterface::class);
    $queueWorkerManager->method('getDefinitions')->willReturn(['known' => []]);

    $service = $this->createService([
      'queue_worker_manager' => $queueWorkerManager,
    ]);

    $result = $service->runQueue('missing', 10);
    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
    $this->assertContains('known', $result['available_queues']);
  }

  public function testRunQueueProcessesItems(): void {
    $queueWorkerManager = $this->createMock(QueueWorkerManagerInterface::class);
    $queueWorkerManager->method('getDefinitions')->willReturn([
      'foo' => ['title' => 'Foo'],
    ]);

    $worker = $this->createMock(QueueWorkerInterface::class);
    $worker->expects($this->exactly(2))->method('processItem');
    $queueWorkerManager->method('createInstance')->with('foo')->willReturn($worker);

    $queue = $this->createMock(QueueInterface::class);
    $item1 = (object) ['data' => ['x' => 1]];
    $item2 = (object) ['data' => ['x' => 2]];
    $queue->method('claimItem')->willReturnOnConsecutiveCalls($item1, $item2, FALSE);
    $queue->expects($this->exactly(2))->method('deleteItem');
    $queue->method('numberOfItems')->willReturn(0);

    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->with('foo')->willReturn($queue);

    $service = $this->createService([
      'queue_worker_manager' => $queueWorkerManager,
      'queue_factory' => $queueFactory,
    ]);

    $result = $service->runQueue('foo', 10);
    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['processed']);
    $this->assertSame(0, $result['failed']);
  }

  public function testUpdateSettingsValidatesAndSavesThreshold(): void {
    $editable = $this->createMock(Config::class);
    $editable->method('get')->with('threshold.autorun')->willReturn(100);
    $editable->expects($this->once())->method('set')->with('threshold.autorun', 200)->willReturnSelf();
    $editable->expects($this->once())->method('save');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('getEditable')->with('system.cron')->willReturn($editable);

    $service = $this->createService(['config_factory' => $configFactory]);

    $invalid = $service->updateSettings(-1);
    $this->assertFalse($invalid['success']);
    $this->assertSame('VALIDATION_ERROR', $invalid['code']);

    $result = $service->updateSettings(200);
    $this->assertTrue($result['success']);
    $this->assertSame(100, $result['changes']['threshold']['old']);
    $this->assertSame(200, $result['changes']['threshold']['new']);
  }

  public function testResetCronKeyStoresNewKey(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())->method('set')->with(
      'system.cron_key',
      $this->callback(static fn(string $key): bool => strlen($key) === 32)
    );

    $service = $this->createService(['state' => $state]);
    $result = $service->resetCronKey();

    $this->assertTrue($result['success']);
    $this->assertSame(32, strlen($result['new_key']));
  }

}
