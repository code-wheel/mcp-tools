<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_sitemap\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_sitemap\Service\SitemapService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_sitemap')]
final class SitemapServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): SitemapService {
    return new SitemapService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['config_factory'] ?? $this->createMock(ConfigFactoryInterface::class),
      $overrides['generator'] ?? $this->createMock(Generator::class),
      $overrides['queue_worker'] ?? $this->createMock(QueueWorker::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
      $overrides['lock'] ?? NULL,
    );
  }

  /**
   * Builds an entity type manager whose simple_sitemap storage returns $map.
   *
   * @param array<string, object> $map
   *   Sitemap entities keyed by id.
   */
  private function entityTypeManagerWithSitemaps(array $map): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($map);
    $storage->method('load')->willReturnCallback(fn($id) => $map[$id] ?? NULL);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('simple_sitemap')->willReturn($storage);
    return $etm;
  }

  public function testGetStatusReturnsAllSitemaps(): void {
    $etm = $this->entityTypeManagerWithSitemaps([
      'default' => $this->createSitemapMock('default', 'Default', TRUE, 100, 1),
    ]);

    $queue = $this->createMock(QueueWorker::class);
    $queue->method('getInitialElementCount')->willReturn(0);
    $queue->method('getQueuedElementCount')->willReturn(0);

    $service = $this->createService([
      'entity_type_manager' => $etm,
      'queue_worker' => $queue,
    ]);
    $result = $service->getStatus();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total_sitemaps']);
    $this->assertArrayHasKey('default', $result['data']['sitemaps']);
    $this->assertSame(100, $result['data']['sitemaps']['default']['link_count']);
  }

  public function testGetSitemapsListsAll(): void {
    $etm = $this->entityTypeManagerWithSitemaps([
      'default' => $this->createSitemapMock('default', 'Default', TRUE, 50, 1),
    ]);

    $service = $this->createService(['entity_type_manager' => $etm]);
    $result = $service->getSitemaps();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('default', $result['data']['sitemaps'][0]['id']);
    $this->assertTrue($result['data']['sitemaps'][0]['is_default']);
  }

  public function testGetSettingsVariantNotFound(): void {
    $service = $this->createService([
      'entity_type_manager' => $this->entityTypeManagerWithSitemaps([]),
    ]);
    $result = $service->getSettings('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testUpdateSettingsRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->updateSettings('default', ['enabled' => TRUE]);

    $this->assertFalse($result['success']);
  }

  public function testRegenerateRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->regenerate();

    $this->assertFalse($result['success']);
  }

  public function testRegenerateVariantNotFound(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService([
      'access_manager' => $accessManager,
      'entity_type_manager' => $this->entityTypeManagerWithSitemaps([]),
    ]);
    $result = $service->regenerate('missing');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetEntitySettingsUnknownEntityType(): void {
    $service = $this->createService([
      'generator' => $this->generatorWithBundleSettings([
        'default' => ['node' => ['article' => ['index' => TRUE]]],
      ]),
    ]);
    $result = $service->getEntitySettings('taxonomy_term');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not configured', $result['error']);
    $this->assertContains('node', $result['available_entity_types']);
  }

  public function testSetEntitySettingsRejectsInvalidPriority(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('node', FALSE)->willReturn(
      $this->createMock(EntityTypeInterface::class)
    );

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->setEntitySettings('node', 'article', ['priority' => '2.0']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid priority', $result['error']);
  }

  public function testSetEntitySettingsRejectsInvalidChangefreq(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('node', FALSE)->willReturn(
      $this->createMock(EntityTypeInterface::class)
    );

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->setEntitySettings('node', 'article', ['changefreq' => 'biweekly']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid changefreq', $result['error']);
  }

  /**
   * Builds a Generator whose entityManager()->getAllBundleSettings() is stubbed.
   *
   * @param array<string, mixed> $allSettings
   *   The getAllBundleSettings() return value (keyed by variant).
   */
  private function generatorWithBundleSettings(array $allSettings): Generator {
    $entityManager = $this->createMock(\Drupal\simple_sitemap\Manager\EntityManager::class);
    $entityManager->method('setSitemaps')->willReturnSelf();
    $entityManager->method('getAllBundleSettings')->willReturn($allSettings);

    $generator = $this->createMock(Generator::class);
    $generator->method('entityManager')->willReturn($entityManager);
    return $generator;
  }

  private function createSitemapMock(string $id, string $label, bool $enabled, int $linkCount, int $chunkCount): object {
    return new class($id, $label, $enabled, $linkCount, $chunkCount) {

      public function __construct(
        private string $id,
        private string $label,
        private bool $enabled,
        private int $linkCount,
        private int $chunkCount,
      ) {}

      public function id(): string {
        return $this->id;
      }

      public function label(): string {
        return $this->label;
      }

      public function status(): bool {
        return $this->enabled;
      }

      public function contentStatus(): int {
        return 1;
      }

      public function getLinkCount(): int {
        return $this->linkCount;
      }

      public function getChunkCount(): int {
        return $this->chunkCount;
      }

      public function hasIndex(): bool {
        return FALSE;
      }

      public function getType(): object {
        return new class {

          public function id(): string {
            return 'default_hreflang';
          }

          public function label(): string {
            return 'Default hreflang';
          }

        };
      }

    };
  }

  /**
   * Regenerate generates in this request, and says what is left.
   *
   * It called generate() in the form mode, which only set up a batch that
   * nothing ran: "regeneration started", and no sitemap changed.
   */
  public function testRegenerateGeneratesAndSaysWhatIsLeft(): void {
    $access = $this->createMock(AccessManager::class);
    $access->method('canWrite')->willReturn(TRUE);
    $generator = $this->createMock(Generator::class);
    $generator->method('setSitemaps')->willReturnSelf();
    $generator->method('rebuildQueue')->willReturnSelf();
    $generator->expects($this->once())->method('generate')->with(QueueWorker::GENERATE_TYPE_BACKEND)->willReturnSelf();
    $queue = $this->createMock(QueueWorker::class);
    $queue->method('getQueuedElementCount')->willReturnOnConsecutiveCalls(0, 40);
    $queue->method('getInitialElementCount')->willReturn(100);

    $result = $this->createService(['access_manager' => $access, 'generator' => $generator, 'queue_worker' => $queue])->regenerate();
    $this->assertTrue($result['success']);
    $this->assertSame(40, $result['data']['remaining'], 'The answer did not say how much is left.');
    $this->assertFalse($result['data']['done']);
    $this->assertStringContainsString('continue: true', $result['data']['message']);
  }

  /**
   * Continue goes on with the queue, and a held lock is refused.
   */
  public function testContinueKeepsTheQueueAndALockIsRefused(): void {
    $access = $this->createMock(AccessManager::class);
    $access->method('canWrite')->willReturn(TRUE);
    $generator = $this->createMock(Generator::class);
    $generator->method('setSitemaps')->willReturnSelf();
    $generator->expects($this->never())->method('rebuildQueue');
    $generator->method('generate')->willReturnSelf();
    $queue = $this->createMock(QueueWorker::class);
    $queue->method('getQueuedElementCount')->willReturnOnConsecutiveCalls(40, 0);
    $queue->method('getInitialElementCount')->willReturn(100);
    $result = $this->createService(['access_manager' => $access, 'generator' => $generator, 'queue_worker' => $queue])->regenerate(NULL, TRUE);
    $this->assertTrue($result['data']['done']);

    $lock = $this->createMock(\Drupal\Core\Lock\LockBackendInterface::class);
    $lock->method('lockMayBeAvailable')->willReturn(FALSE);
    $held = $this->createService(['access_manager' => $access, 'lock' => $lock])->regenerate();
    $this->assertFalse($held['success'], 'A generation that could not lock said success.');
  }

}
