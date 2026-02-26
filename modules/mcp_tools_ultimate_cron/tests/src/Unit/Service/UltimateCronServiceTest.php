<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ultimate_cron\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_ultimate_cron\Service\UltimateCronService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_ultimate_cron')]
final class UltimateCronServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): UltimateCronService {
    $jobStorage = $overrides['job_storage'] ?? $this->createMock(EntityStorageInterface::class);

    $entityTypeManager = $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('ultimate_cron_job')
      ->willReturn($jobStorage);

    return new UltimateCronService(
      $entityTypeManager,
      $overrides['database'] ?? $this->createMock(Connection::class),
      $overrides['logger_factory'] ?? $this->createMock(LoggerChannelFactoryInterface::class),
    );
  }

  public function testListJobsReturnsSortedList(): void {
    $jobA = $this->createJobMock('b_job', 'Job B', 'system', TRUE, FALSE);
    $jobB = $this->createJobMock('a_job', 'Job A', 'node', TRUE, FALSE);

    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('loadMultiple')->willReturn(['b_job' => $jobA, 'a_job' => $jobB]);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->createEmptySelectMock());

    $service = $this->createService([
      'job_storage' => $jobStorage,
      'database' => $database,
    ]);
    $result = $service->listJobs();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['count']);
    // Should be sorted by ID.
    $this->assertSame('a_job', $result['data']['jobs'][0]['id']);
    $this->assertSame('b_job', $result['data']['jobs'][1]['id']);
  }

  public function testGetJobNotFound(): void {
    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('missing')->willReturn(NULL);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->getJob('missing');

    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
  }

  public function testRunJobNotFound(): void {
    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('missing')->willReturn(NULL);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->runJob('missing');

    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
  }

  public function testRunJobDisabled(): void {
    $job = $this->createJobMock('test_job', 'Test', 'system', FALSE, FALSE);

    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('test_job')->willReturn($job);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->runJob('test_job');

    $this->assertFalse($result['success']);
    $this->assertSame('JOB_DISABLED', $result['code']);
  }

  public function testRunJobLocked(): void {
    $job = $this->createJobMock('test_job', 'Test', 'system', TRUE, TRUE);

    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('test_job')->willReturn($job);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->runJob('test_job');

    $this->assertFalse($result['success']);
    $this->assertSame('JOB_LOCKED', $result['code']);
  }

  public function testEnableJobAlreadyEnabled(): void {
    $job = $this->createJobMock('test_job', 'Test', 'system', TRUE, FALSE);

    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('test_job')->willReturn($job);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->enableJob('test_job');

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
  }

  public function testDisableJobAlreadyDisabled(): void {
    $job = $this->createJobMock('test_job', 'Test', 'system', FALSE, FALSE);

    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('test_job')->willReturn($job);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->disableJob('test_job');

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
  }

  public function testGetJobLogsNotFound(): void {
    $jobStorage = $this->createMock(EntityStorageInterface::class);
    $jobStorage->method('load')->with('missing')->willReturn(NULL);

    $service = $this->createService(['job_storage' => $jobStorage]);
    $result = $service->getJobLogs('missing');

    $this->assertFalse($result['success']);
    $this->assertSame('NOT_FOUND', $result['code']);
  }

  private function createJobMock(string $id, string $label, string $module, bool $enabled, bool $locked): object {
    return new class($id, $label, $module, $enabled, $locked) {

      public function __construct(
        private string $id,
        private string $label,
        private string $module,
        private bool $enabled,
        private bool $locked,
      ) {}

      public function id(): string {
        return $this->id;
      }

      public function label(): string {
        return $this->label;
      }

      public function getModule(): string {
        return $this->module;
      }

      public function status(): bool {
        return $this->enabled;
      }

      public function isLocked(): bool {
        return $this->locked;
      }

      public function run(): void {}

      public function enable(): void {}

      public function disable(): void {}

      public function save(): void {}

      public function getCallback(): string {
        return $this->module . '_cron';
      }

      public function getPlugin(string $type): ?object {
        return new class($type) {

          public function __construct(private string $type) {}

          public function getPluginId(): string {
            return $this->type . '_default';
          }

        };
      }

      public function get(string $key): mixed {
        return NULL;
      }

    };
  }

  private function createEmptySelectMock(): SelectInterface {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchObject')->willReturn(FALSE);

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

}
