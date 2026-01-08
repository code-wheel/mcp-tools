<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_blocks\Unit\Service;

use Drupal\block\BlockInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_blocks\Service\BlockService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for BlockService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_blocks\Service\BlockService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_blocks')]
class BlockServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected BlockManagerInterface $blockManager;
  protected ThemeHandlerInterface $themeHandler;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $blockStorage;
  protected bool $themeExists = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->themeHandler = $this->createMock(ThemeHandlerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->blockStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('block')
      ->willReturn($this->blockStorage);

    // Default theme handler behavior.
    $this->themeHandler->method('getDefault')->willReturn('olivero');
    $this->themeExists = TRUE;
    $this->themeHandler->method('themeExists')
      ->willReturnCallback(function (string $theme): bool {
        return $this->themeExists;
      });
  }

  /**
   * Creates a BlockService instance.
   */
  protected function createBlockService(): BlockService {
    return new BlockService(
      $this->entityTypeManager,
      $this->blockManager,
      $this->themeHandler,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testPlaceBlockAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createBlockService();
    $result = $service->placeBlock('system_branding_block', 'header');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testPlaceBlockPluginNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->blockManager->method('getDefinitions')->willReturn([]);

    $service = $this->createBlockService();
    $result = $service->placeBlock('nonexistent_block', 'header');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testPlaceBlockThemeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->blockManager->method('getDefinitions')->willReturn([
      'system_branding_block' => ['admin_label' => 'Branding'],
    ]);
    $this->themeExists = FALSE;

    $service = $this->createBlockService();
    $result = $service->placeBlock('system_branding_block', 'header', ['theme' => 'nonexistent_theme']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Theme', $result['error']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testRemoveBlockAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createBlockService();
    $result = $service->removeBlock('olivero_branding');

    $this->assertFalse($result['success']);
  }

  public function testRemoveBlockNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->blockStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createBlockService();
    $result = $service->removeBlock('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testRemoveBlockSuccess(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $block = $this->createMock(BlockInterface::class);
    $block->method('getPluginId')->willReturn('system_branding_block');
    $block->method('getRegion')->willReturn('header');
    $block->method('getTheme')->willReturn('olivero');
    $block->expects($this->once())->method('delete');

    $this->blockStorage->method('load')->with('olivero_branding')->willReturn($block);

    $service = $this->createBlockService();
    $result = $service->removeBlock('olivero_branding');

    $this->assertTrue($result['success']);
    $this->assertEquals('olivero_branding', $result['data']['block_id']);
  }

  public function testConfigureBlockAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createBlockService();
    $result = $service->configureBlock('my_block', ['weight' => 10]);

    $this->assertFalse($result['success']);
  }

  public function testConfigureBlockNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->blockStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createBlockService();
    $result = $service->configureBlock('nonexistent', ['weight' => 10]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testConfigureBlockNoChanges(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $block = $this->createMock(BlockInterface::class);
    $this->blockStorage->method('load')->with('my_block')->willReturn($block);

    $service = $this->createBlockService();
    $result = $service->configureBlock('my_block', []);

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
    $this->assertStringContainsString('No changes', $result['data']['message']);
  }

  public function testListAvailableBlocks(): void {
    $this->blockManager->method('getDefinitions')->willReturn([
      'system_branding_block' => [
        'admin_label' => 'Branding',
        'category' => 'System',
        'provider' => 'system',
      ],
      'system_menu_block:main' => [
        'admin_label' => 'Main navigation',
        'category' => 'Menus',
        'provider' => 'system',
      ],
    ]);

    $service = $this->createBlockService();
    $result = $service->listAvailableBlocks();

    $this->assertTrue($result['success']);
    $this->assertEquals(2, $result['data']['count']);
    $this->assertCount(2, $result['data']['blocks']);
  }

  public function testListRegionsThemeNotFound(): void {
    $this->themeExists = FALSE;

    $service = $this->createBlockService();
    $result = $service->listRegions('nonexistent_theme');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

}
