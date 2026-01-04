<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\mcp_tools\Service\QueueService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools\Service\QueueService
 * @group mcp_tools
 */
final class QueueServiceTest extends UnitTestCase {

  /**
   * @covers ::getQueueStatus
   */
  public function testGetQueueStatusIncludesWorkersAndDatabaseQueues(): void {
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueWorkerManager = $this->createMock(QueueWorkerManagerInterface::class);

    $queueWorkerManager->method('getDefinitions')->willReturn([
      'foo' => ['title' => 'Foo', 'cron' => ['time' => 30]],
      'bar' => ['title' => 'Bar'],
    ]);

    $queuesByName = [
      'foo' => 5,
      'bar' => 0,
      'baz' => 10,
    ];
    $queueFactory->method('get')->willReturnCallback(function (string $name) use ($queuesByName): QueueInterface {
      $queue = $this->createMock(QueueInterface::class);
      $queue->method('numberOfItems')->willReturn($queuesByName[$name] ?? 0);
      return $queue;
    });

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('queue')->willReturn(TRUE);

    $select = new class() {
      public function fields(string $table, array $fields): static { return $this; }
      public function groupBy(string $field): static { return $this; }
      public function execute(): object {
        return new class() {
          public function fetchCol(): array { return ['baz']; }
        };
      }
    };

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')->with('queue', 'q')->willReturn($select);

    $service = new QueueService($queueFactory, $queueWorkerManager, $database);

    $status = $service->getQueueStatus();
    $this->assertSame(3, $status['total_queues']);
    $this->assertSame(2, $status['queues_with_items']);
    $this->assertSame(15, $status['total_items']);

    $queues = $status['queues'];
    $this->assertSame('baz', $queues[0]['name']);
    $this->assertSame(10, $queues[0]['items']);
  }

  /**
   * @covers ::getQueueDetails
   */
  public function testGetQueueDetailsIncludesOldestItemAgeWhenAvailable(): void {
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueWorkerManager = $this->createMock(QueueWorkerManagerInterface::class);
    $queueWorkerManager->method('getDefinitions')->willReturn([
      'foo' => ['title' => 'Foo'],
    ]);

    $queue = $this->createMock(QueueInterface::class);
    $queue->method('numberOfItems')->willReturn(3);
    $queueFactory->method('get')->with('foo')->willReturn($queue);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('queue')->willReturn(TRUE);

    $oldest = time() - 100;
    $select = new class($oldest) {
      public function __construct(private readonly int $oldest) {}
      public function fields(string $table, array $fields): static { return $this; }
      public function condition(string $field, mixed $value, ?string $operator = NULL): static { return $this; }
      public function orderBy(string $field, string $direction = 'ASC'): static { return $this; }
      public function range(int $start, int $length): static { return $this; }
      public function execute(): object {
        return new class($this->oldest) {
          public function __construct(private readonly int $oldest) {}
          public function fetchField(): int { return $this->oldest; }
        };
      }
    };

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')->with('queue', 'q')->willReturn($select);

    $service = new QueueService($queueFactory, $queueWorkerManager, $database);
    $details = $service->getQueueDetails('foo');

    $this->assertSame('foo', $details['name']);
    $this->assertSame(3, $details['items']);
    $this->assertTrue($details['has_worker']);
    $this->assertArrayHasKey('oldest_item_age', $details);
    $this->assertGreaterThanOrEqual(95, $details['oldest_item_age']);
    $this->assertLessThanOrEqual(105, $details['oldest_item_age']);
  }

}
