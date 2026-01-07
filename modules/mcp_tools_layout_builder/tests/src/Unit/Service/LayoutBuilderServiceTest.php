<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_layout_builder\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Section;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools_layout_builder\Service\LayoutBuilderService
 * @group mcp_tools_layout_builder
 */
final class LayoutBuilderServiceTest extends UnitTestCase {

  private function createDisplay(array $sections, bool $enabled = TRUE): object {
    return new class($sections, $enabled) {
      public function __construct(private array $sections, private bool $enabled) {}
      private bool $overridable = FALSE;

      public function enableLayoutBuilder(): void { $this->enabled = TRUE; }
      public function disableLayoutBuilder(): void { $this->enabled = FALSE; }
      public function isLayoutBuilderEnabled(): bool { return $this->enabled; }
      public function setOverridable(bool $allow): void { $this->overridable = $allow; }
      public function isOverridable(): bool { return $this->overridable; }
      public function getSections(): array { return $this->sections; }
      public function insertSection(int $delta, Section $section): void {
        array_splice($this->sections, $delta, 0, [$section]);
      }
      public function removeSection(int $delta): void {
        unset($this->sections[$delta]);
        $this->sections = array_values($this->sections);
      }
      public function setSection(int $delta, Section $section): void {
        $this->sections[$delta] = $section;
      }
      public function save(): void {}
    };
  }

  private function createService(object $display, LayoutPluginManagerInterface $layoutPluginManager, BlockManagerInterface $blockManager, UuidInterface $uuid, AccessManager $accessManager, AuditLogger $auditLogger): LayoutBuilderService {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('entity_view_display')->willReturn($storage);

    return new LayoutBuilderService(
      $entityTypeManager,
      $this->createMock(EntityDisplayRepositoryInterface::class),
      $layoutPluginManager,
      $blockManager,
      $uuid,
      $accessManager,
      $auditLogger,
    );
  }

  /**
   * @covers ::enableLayoutBuilder
   */
  public function testEnableLayoutBuilderRespectsWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn(['success' => FALSE, 'code' => 'INSUFFICIENT_SCOPE']);

    $service = $this->createService(
      $this->createDisplay([]),
      $this->createMock(LayoutPluginManagerInterface::class),
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(UuidInterface::class),
      $accessManager,
      $this->createMock(AuditLogger::class),
    );

    $result = $service->enableLayoutBuilder('node', 'article');
    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::enableLayoutBuilder
   * @covers ::getOrCreateDisplay
   * @covers ::loadDisplay
   */
  public function testEnableLayoutBuilderEnablesAndSaves(): void {
    $display = $this->createDisplay([], FALSE);

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $auditLogger = $this->createMock(AuditLogger::class);
    $auditLogger->expects($this->once())->method('logSuccess');

    $service = $this->createService(
      $display,
      $this->createMock(LayoutPluginManagerInterface::class),
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(UuidInterface::class),
      $accessManager,
      $auditLogger,
    );

    $result = $service->enableLayoutBuilder('node', 'article');
    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['layout_builder_enabled']);
  }

  /**
   * @covers ::addSection
   */
  public function testAddSectionValidatesLayoutDefinition(): void {
    $display = $this->createDisplay([], TRUE);

    $layoutPluginManager = $this->createMock(LayoutPluginManagerInterface::class);
    $layoutPluginManager->method('hasDefinition')->with('layout_onecol')->willReturn(FALSE);

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(
      $display,
      $layoutPluginManager,
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(UuidInterface::class),
      $accessManager,
      $this->createMock(AuditLogger::class),
    );

    $result = $service->addSection('node', 'article', 'layout_onecol', 0);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * @covers ::addSection
   * @covers ::removeSection
   */
  public function testAddAndRemoveSectionUpdatesCount(): void {
    $display = $this->createDisplay([new Section('layout_onecol')], TRUE);

    $layoutPluginManager = $this->createMock(LayoutPluginManagerInterface::class);
    $layoutPluginManager->method('hasDefinition')->willReturn(TRUE);

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(
      $display,
      $layoutPluginManager,
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(UuidInterface::class),
      $accessManager,
      $this->createMock(AuditLogger::class),
    );

    $added = $service->addSection('node', 'article', 'layout_onecol', 1);
    $this->assertTrue($added['success']);
    $this->assertSame(2, $added['data']['section_count']);

    $removed = $service->removeSection('node', 'article', 0);
    $this->assertTrue($removed['success']);
    $this->assertSame(1, $removed['data']['section_count']);
  }

  /**
   * @covers ::addBlock
   * @covers ::removeBlock
   */
  public function testAddAndRemoveBlockLifecycle(): void {
    $display = $this->createDisplay([new Section('layout_onecol')], TRUE);

    $layoutDefinition = new class() {
      public function getRegions(): array {
        return ['content' => ['label' => 'Content']];
      }
    };

    $layoutPluginManager = $this->createMock(LayoutPluginManagerInterface::class);
    $layoutPluginManager->method('hasDefinition')->willReturn(TRUE);
    $layoutPluginManager->method('getDefinition')->willReturn($layoutDefinition);

    $blockManager = $this->createMock(BlockManagerInterface::class);
    $blockManager->method('hasDefinition')->with('system_powered_by_block')->willReturn(TRUE);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('uuid-123');

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService(
      $display,
      $layoutPluginManager,
      $blockManager,
      $uuid,
      $accessManager,
      $this->createMock(AuditLogger::class),
    );

    $added = $service->addBlock('node', 'article', 0, 'content', 'system_powered_by_block');
    $this->assertTrue($added['success']);
    $this->assertSame('uuid-123', $added['data']['component_uuid']);

    $removed = $service->removeBlock('node', 'article', 'uuid-123');
    $this->assertTrue($removed['success']);
    $this->assertSame('uuid-123', $removed['data']['removed_block_uuid']);
  }

  /**
   * @covers ::listLayoutPlugins
   */
  public function testListLayoutPluginsSortsByCategoryThenLabel(): void {
    $display = $this->createDisplay([], TRUE);

    $layoutPluginManager = $this->createMock(LayoutPluginManagerInterface::class);
    $layoutPluginManager->method('getDefinitions')->willReturn([
      'b' => new class() {
        public function getRegions(): array { return ['content' => ['label' => 'Content']]; }
        public function getLabel(): string { return 'B'; }
        public function getCategory(): string { return 'Z'; }
        public function getDefaultRegion(): string { return 'content'; }
      },
      'a' => new class() {
        public function getRegions(): array { return ['content' => ['label' => 'Content']]; }
        public function getLabel(): string { return 'A'; }
        public function getCategory(): string { return 'A'; }
        public function getDefaultRegion(): string { return 'content'; }
      },
    ]);

    $service = $this->createService(
      $display,
      $layoutPluginManager,
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(AccessManager::class),
      $this->createMock(AuditLogger::class),
    );

    $result = $service->listLayoutPlugins();
    $this->assertTrue($result['success']);
    $layouts = $result['data']['layouts'];
    $this->assertSame('a', $layouts[0]['id']);
  }

}
