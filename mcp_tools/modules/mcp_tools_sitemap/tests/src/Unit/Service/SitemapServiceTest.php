<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_sitemap\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for SitemapService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_sitemap\Service\SitemapService
 * @group mcp_tools_sitemap
 */
class SitemapServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $sitemapStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->sitemapStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['simple_sitemap', $this->sitemapStorage],
      ]);
  }

  /**
   * Creates a SitemapService instance.
   */
  protected function createService(): SitemapService {
    return new SitemapService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listSitemaps
   */
  public function testListSitemapsEmpty(): void {
    $this->sitemapStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listSitemaps();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['sitemaps']);
  }

  /**
   * @covers ::regenerate
   */
  public function testRegenerateAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->regenerate();

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::getSitemapStatus
   */
  public function testGetSitemapStatusNotFound(): void {
    $this->sitemapStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getSitemapStatus('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::configureBundleSettings
   */
  public function testConfigureBundleSettingsAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->configureBundleSettings('node', 'article', []);

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::getBundleSettings
   */
  public function testGetBundleSettingsSuccess(): void {
    $service = $this->createService();
    $result = $service->getBundleSettings('node', 'article');

    // Should return success even with empty settings.
    $this->assertTrue($result['success']);
  }

  /**
   * @covers ::clearSitemapCache
   */
  public function testClearSitemapCacheAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->clearSitemapCache();

    $this->assertFalse($result['success']);
  }

}
