<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_sitemap\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Manager\SitemapManager;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_sitemap\Service\SitemapService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_sitemap')]
final class SitemapServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): SitemapService {
    return new SitemapService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['config_factory'] ?? $this->createMock(ConfigFactoryInterface::class),
      $overrides['generator'] ?? $this->createMock(Generator::class),
      $overrides['sitemap_manager'] ?? $this->createMock(SitemapManager::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testGetStatusReturnsAllSitemaps(): void {
    $sitemap = $this->createSitemapMock('default', 'Default', TRUE, 100, 1);

    $sitemapManager = $this->createMock(SitemapManager::class);
    $sitemapManager->method('getSitemaps')->willReturn(['default' => $sitemap]);

    $generator = $this->createMock(Generator::class);
    $generator->method('getQueueStatus')->willReturn(['total' => 0, 'processed' => 0]);

    $service = $this->createService([
      'sitemap_manager' => $sitemapManager,
      'generator' => $generator,
    ]);
    $result = $service->getStatus();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total_sitemaps']);
    $this->assertArrayHasKey('default', $result['data']['sitemaps']);
    $this->assertSame(100, $result['data']['sitemaps']['default']['link_count']);
  }

  public function testGetSitemapsListsAll(): void {
    $sitemap = $this->createSitemapMock('default', 'Default', TRUE, 50, 1);

    $sitemapManager = $this->createMock(SitemapManager::class);
    $sitemapManager->method('getSitemaps')->willReturn(['default' => $sitemap]);

    $service = $this->createService(['sitemap_manager' => $sitemapManager]);
    $result = $service->getSitemaps();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('default', $result['data']['sitemaps'][0]['id']);
    $this->assertTrue($result['data']['sitemaps'][0]['is_default']);
  }

  public function testGetSettingsVariantNotFound(): void {
    $sitemapManager = $this->createMock(SitemapManager::class);
    $sitemapManager->method('getSitemap')->with('nonexistent')->willReturn(NULL);

    $service = $this->createService(['sitemap_manager' => $sitemapManager]);
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

    $sitemapManager = $this->createMock(SitemapManager::class);
    $sitemapManager->method('getSitemap')->with('missing')->willReturn(NULL);

    $service = $this->createService([
      'access_manager' => $accessManager,
      'sitemap_manager' => $sitemapManager,
    ]);
    $result = $service->regenerate('missing');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetEntitySettingsUnknownEntityType(): void {
    $generator = $this->createMock(Generator::class);
    $generator->method('getBundleSettings')->willReturn([
      'node' => ['article' => ['index' => TRUE]],
    ]);

    $service = $this->createService(['generator' => $generator]);
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
      $this->createMock(\Drupal\Core\Entity\EntityTypeInterface::class)
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
      $this->createMock(\Drupal\Core\Entity\EntityTypeInterface::class)
    );

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'access_manager' => $accessManager,
    ]);
    $result = $service->setEntitySettings('node', 'article', ['changefreq' => 'biweekly']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid changefreq', $result['error']);
  }

  private function createSitemapMock(string $id, string $label, bool $enabled, int $linkCount, int $chunkCount): object {
    $type = new \stdClass();
    $type->id = fn() => 'default_hreflang';
    $type->label = fn() => 'Default hreflang';

    $sitemap = $this->createMock(\stdClass::class);
    // Use a real anonymous class to control behavior.
    $sitemap = new class($id, $label, $enabled, $linkCount, $chunkCount) {

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

      public function getStatus(): string {
        return 'published';
      }

      public function getLinkCount(): int {
        return $this->linkCount;
      }

      public function getChunkCount(): int {
        return $this->chunkCount;
      }

      public function getContent(): array {
        return ['<?xml version="1.0"?>'];
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

    return $sitemap;
  }

}
