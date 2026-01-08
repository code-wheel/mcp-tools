<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_image_styles\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\ImageEffectInterface;
use Drupal\image\ImageEffectManager;
use Drupal\image\ImageStyleInterface;
use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_image_styles\Service\ImageStyleService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_image_styles')]
final class ImageStyleServiceTest extends UnitTestCase {

  public function testCreateImageStyleValidatesNameAndExisting(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnMap([
      ['exists', (object) ['id' => 'exists']],
      ['bad-name', NULL],
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('image_style')->willReturn($storage);

    $service = new ImageStyleService($entityTypeManager, $this->createMock(ImageEffectManager::class));

    $exists = $service->createImageStyle('exists', 'Exists');
    $this->assertFalse($exists['success']);
    $this->assertSame('ALREADY_EXISTS', $exists['code']);

    $invalid = $service->createImageStyle('bad-name', 'Bad');
    $this->assertFalse($invalid['success']);
    $this->assertSame('VALIDATION_ERROR', $invalid['code']);
  }

  public function testDeleteImageStyleReturnsUsageWhenNotForced(): void {
    $style = $this->createMock(ImageStyleInterface::class);
    $style->method('label')->willReturn('Test Style');

    $styleStorage = $this->createMock(EntityStorageInterface::class);
    $styleStorage->method('load')->with('test')->willReturn($style);

    $fieldConfig = new class() {
      public function id(): string { return 'node.article.field_image'; }
      public function getSettings(): array { return ['preview_image_style' => 'test']; }
    };
    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn([$fieldConfig]);

    $display = new class() {
      public function id(): string { return 'node.article.default'; }
      public function getComponents(): array {
        return ['field_image' => ['settings' => ['image_style' => 'test']]];
      }
    };
    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('loadMultiple')->willReturn([$display]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['image_style', $styleStorage],
      ['field_config', $fieldConfigStorage],
      ['entity_view_display', $displayStorage],
    ]);

    $service = new ImageStyleService($entityTypeManager, $this->createMock(ImageEffectManager::class));

    $result = $service->deleteImageStyle('test', FALSE);
    $this->assertFalse($result['success']);
    $this->assertSame('ENTITY_IN_USE', $result['code']);
    $this->assertNotEmpty($result['usage']);

    $style->expects($this->once())->method('delete');
    $forced = $service->deleteImageStyle('test', TRUE);
    $this->assertTrue($forced['success']);
  }

  public function testAddRemoveAndListEffects(): void {
    $style = $this->createMock(ImageStyleInterface::class);
    $style->method('addImageEffect')->willReturn('uuid-1');
    $style->expects($this->exactly(2))->method('save');

    $effect = $this->createMock(ImageEffectInterface::class);
    $effect->method('label')->willReturn('Scale');

    $style->method('getEffect')->willReturn($effect);
    $style->expects($this->once())->method('deleteImageEffect')->with($effect);

    $styleStorage = $this->createMock(EntityStorageInterface::class);
    $styleStorage->method('load')->with('test')->willReturn($style);

    $effects = $this->createMock(ImageEffectManager::class);
    $effects->method('getDefinitions')->willReturn([
      'image_scale' => ['label' => 'Scale', 'description' => 'Scale'],
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('image_style')->willReturn($styleStorage);

    $service = new ImageStyleService($entityTypeManager, $effects);

    $unknown = $service->addImageEffect('test', 'missing', []);
    $this->assertFalse($unknown['success']);
    $this->assertSame('VALIDATION_ERROR', $unknown['code']);

    $added = $service->addImageEffect('test', 'image_scale', ['width' => 100]);
    $this->assertTrue($added['success']);
    $this->assertSame('uuid-1', $added['effect_uuid']);

    $removed = $service->removeImageEffect('test', 'uuid-1');
    $this->assertTrue($removed['success']);

    $list = $service->listImageEffects();
    $this->assertSame(1, $list['total']);
    $this->assertSame('image_scale', $list['effects'][0]['id']);
  }

}
