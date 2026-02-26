<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_scheduler\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_scheduler\Service\SchedulerService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_scheduler\Service\SchedulerService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_scheduler')]
final class SchedulerServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): SchedulerService {
    return new SchedulerService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['current_user'] ?? $this->createMock(AccountProxyInterface::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
      $overrides['time'] ?? $this->createMock(TimeInterface::class),
    );
  }

  public function testSchedulePublishRejectsNonNodeEntityType(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->schedulePublish('taxonomy_term', 1, time() + 3600);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  public function testSchedulePublishRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->schedulePublish('node', 1, time() + 3600);

    $this->assertFalse($result['success']);
  }

  public function testSchedulePublishRejectsPastTimestamp(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(2000000000);

    $node = $this->createNodeMock(1, 'Test', 'article', TRUE, TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($node);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
      'time' => $time,
    ]);
    $result = $service->schedulePublish('node', 1, 1000000000);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('future', $result['error']);
  }

  public function testSchedulePublishNodeNotFound(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->schedulePublish('node', 999, time() + 3600);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testScheduleUnpublishRejectsNonNodeEntityType(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->scheduleUnpublish('user', 1, time() + 3600);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  public function testCancelScheduleRejectsNonNodeEntityType(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->cancelSchedule('taxonomy_term', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  public function testGetScheduleRejectsNonNodeEntityType(): void {
    $service = $this->createService();
    $result = $service->getSchedule('user', 1);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not supported', $result['error']);
  }

  public function testGetScheduleNodeNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getSchedule('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  private function createNodeMock(int $id, string $title, string $bundle, bool $hasPublishOn, bool $hasUnpublishOn): object {
    $node = $this->createMock(\Drupal\Core\Entity\ContentEntityInterface::class);
    $node->method('id')->willReturn($id);
    $node->method('getTitle')->willReturn($title);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturnCallback(function (string $field) use ($hasPublishOn, $hasUnpublishOn) {
      if ($field === 'publish_on') {
        return $hasPublishOn;
      }
      if ($field === 'unpublish_on') {
        return $hasUnpublishOn;
      }
      return FALSE;
    });

    return $node;
  }

}
