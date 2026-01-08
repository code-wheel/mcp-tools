<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_redirect\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for RedirectService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_redirect\Service\RedirectService
 * @group mcp_tools_redirect
 */
class RedirectServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $redirectStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->redirectStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($this->redirectStorage);
  }

  /**
   * Creates a RedirectService instance.
   */
  protected function createService(): RedirectService {
    return new RedirectService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::listRedirects
   */
  public function testListRedirectsEmpty(): void {
    $countQuery = $this->createMock(QueryInterface::class);
    $countQuery->method('accessCheck')->willReturnSelf();
    $countQuery->method('count')->willReturnSelf();
    $countQuery->method('execute')->willReturn(0);

    $listQuery = $this->createMock(QueryInterface::class);
    $listQuery->method('accessCheck')->willReturnSelf();
    $listQuery->method('sort')->willReturnSelf();
    $listQuery->method('range')->willReturnSelf();
    $listQuery->method('execute')->willReturn([]);

    $this->redirectStorage->method('getQuery')
      ->willReturnOnConsecutiveCalls($countQuery, $listQuery);
    $this->redirectStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listRedirects();

    $this->assertTrue($result['success']);
    $this->assertEquals(0, $result['data']['total']);
    $this->assertEmpty($result['data']['redirects']);
  }

  /**
   * @covers ::getRedirect
   */
  public function testGetRedirectNotFound(): void {
    $this->redirectStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getRedirect(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::createRedirect
   */
  public function testCreateRedirectAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->createRedirect('/old', '/new');

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::createRedirect
   */
  public function testCreateRedirectInvalidStatusCode(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->createRedirect('/old', '/new', 404);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid status code', $result['error']);
  }

  /**
   * @covers ::createRedirect
   */
  public function testCreateRedirectEmptySource(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->createRedirect('', '/new');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('cannot be empty', $result['error']);
  }

  /**
   * @covers ::createRedirect
   */
  public function testCreateRedirectEmptyDestination(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService();
    $result = $service->createRedirect('/old', '');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('cannot be empty', $result['error']);
  }

  /**
   * @covers ::deleteRedirect
   */
  public function testDeleteRedirectAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteRedirect(1);

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteRedirect
   */
  public function testDeleteRedirectNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->redirectStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteRedirect(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::findBySource
   */
  public function testFindBySourceNotFound(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $this->redirectStorage->method('getQuery')->willReturn($query);

    $service = $this->createService();
    $result = $service->findBySource('/nonexistent');

    // findBySource returns success=true with found=false when no redirect exists.
    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['found']);
    $this->assertStringContainsString('No redirect found', $result['data']['message']);
  }

  /**
   * @covers ::listRedirects
   */
  public function testListRedirectsWithPagination(): void {
    $countQuery = $this->createMock(QueryInterface::class);
    $countQuery->method('accessCheck')->willReturnSelf();
    $countQuery->method('count')->willReturnSelf();
    $countQuery->method('execute')->willReturn(50);

    $listQuery = $this->createMock(QueryInterface::class);
    $listQuery->method('accessCheck')->willReturnSelf();
    $listQuery->method('sort')->willReturnSelf();
    $listQuery->method('range')->willReturnSelf();
    $listQuery->method('execute')->willReturn([]);

    $this->redirectStorage->method('getQuery')
      ->willReturnOnConsecutiveCalls($countQuery, $listQuery);
    $this->redirectStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listRedirects(10, 20);

    $this->assertTrue($result['success']);
    $this->assertEquals(50, $result['data']['total']);
    $this->assertEquals(10, $result['data']['limit']);
    $this->assertEquals(20, $result['data']['offset']);
  }

}
