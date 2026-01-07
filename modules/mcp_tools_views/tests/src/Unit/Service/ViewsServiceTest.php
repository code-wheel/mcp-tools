<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_views\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_views\Service\ViewsService;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;

/**
 * Tests for ViewsService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_views\Service\ViewsService
 * @group mcp_tools_views
 */
class ViewsServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $viewStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->viewStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($this->viewStorage);
  }

  /**
   * Creates a ViewsService instance.
   */
  protected function createViewsService(): ViewsService {
    return new ViewsService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  /**
   * @covers ::createView
   */
  public function testCreateViewAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createViewsService();
    $result = $service->createView('test_view', 'Test View');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  /**
   * @covers ::createView
   * @dataProvider invalidMachineNameProvider
   */
  public function testCreateViewInvalidMachineName(string $invalidName): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createViewsService();
    $result = $service->createView($invalidName, 'Invalid View');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid machine name', $result['error']);
  }

  /**
   * Data provider for invalid machine names.
   */
  public static function invalidMachineNameProvider(): array {
    return [
      'starts with number' => ['1view'],
      'uppercase letters' => ['MyView'],
      'has spaces' => ['my view'],
      'has dashes' => ['my-view'],
      'special chars' => ['view@test'],
    ];
  }

  /**
   * @covers ::createView
   */
  public function testCreateViewAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingView = $this->createMock(View::class);
    $this->viewStorage->method('load')->with('existing_view')->willReturn($existingView);

    $service = $this->createViewsService();
    $result = $service->createView('existing_view', 'Existing View');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * @covers ::createView
   * @dataProvider invalidBaseTableProvider
   */
  public function testCreateViewInvalidBaseTable(string $invalidTable): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->viewStorage->method('load')->willReturn(NULL);

    $service = $this->createViewsService();
    $result = $service->createView('test_view', 'Test View', $invalidTable);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid base table', $result['error']);
  }

  /**
   * Data provider for invalid base tables.
   */
  public static function invalidBaseTableProvider(): array {
    return [
      'invalid table' => ['invalid_table'],
      'nonexistent' => ['foobar'],
      'sql injection attempt' => ['users; DROP TABLE users;'],
    ];
  }

  /**
   * @covers ::deleteView
   */
  public function testDeleteViewAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createViewsService();
    $result = $service->deleteView('my_view');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::deleteView
   */
  public function testDeleteViewNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->viewStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createViewsService();
    $result = $service->deleteView('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::deleteView
   * @dataProvider coreViewsProvider
   */
  public function testDeleteViewProtectsCoreViews(string $coreViewId): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $coreView = $this->createMock(View::class);
    $this->viewStorage->method('load')->with($coreViewId)->willReturn($coreView);

    $service = $this->createViewsService();
    $result = $service->deleteView($coreViewId);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Cannot delete core view', $result['error']);
  }

  /**
   * Data provider for core views.
   */
  public static function coreViewsProvider(): array {
    return [
      'frontpage' => ['frontpage'],
      'taxonomy_term' => ['taxonomy_term'],
      'content' => ['content'],
      'files' => ['files'],
      'user_admin_people' => ['user_admin_people'],
      'watchdog' => ['watchdog'],
    ];
  }

  /**
   * @covers ::addDisplay
   */
  public function testAddDisplayAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createViewsService();
    $result = $service->addDisplay('my_view', 'page');

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::addDisplay
   */
  public function testAddDisplayViewNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->viewStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createViewsService();
    $result = $service->addDisplay('nonexistent', 'page');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::addDisplay
   */
  public function testAddDisplayInvalidType(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $view = $this->createMock(View::class);
    $this->viewStorage->method('load')->with('my_view')->willReturn($view);

    $service = $this->createViewsService();
    $result = $service->addDisplay('my_view', 'invalid_type');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid display type', $result['error']);
  }

  /**
   * @covers ::setViewStatus
   */
  public function testSetViewStatusAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createViewsService();
    $result = $service->setViewStatus('my_view', TRUE);

    $this->assertFalse($result['success']);
  }

  /**
   * @covers ::setViewStatus
   */
  public function testSetViewStatusNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->viewStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createViewsService();
    $result = $service->setViewStatus('nonexistent', TRUE);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::createContentListView
   */
  public function testCreateContentListViewAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createViewsService();
    $result = $service->createContentListView('articles', 'Articles');

    $this->assertFalse($result['success']);
  }

}
