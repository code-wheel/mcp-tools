<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_templates\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for TemplateService.
 *
 * @coversDefaultClass \Drupal\mcp_tools_templates\Service\TemplateService
 * @group mcp_tools_templates
 */
final class TemplateServiceTest extends UnitTestCase {

  private ConfigFactoryInterface $configFactory;
  private AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
  }

  /**
   * @covers ::listTemplates
   */
  public function testListTemplatesReturnsExpectedBasics(): void {
    $service = new TemplateService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $this->createMock(AccessManager::class),
      $this->auditLogger,
    );

    $result = $service->listTemplates();

    $this->assertTrue($result['success']);
    $this->assertSame(4, $result['data']['count']);
    $this->assertCount(4, $result['data']['templates']);

    $ids = array_column($result['data']['templates'], 'id');
    $this->assertContains('blog', $ids);
    $this->assertContains('portfolio', $ids);
    $this->assertContains('business', $ids);
    $this->assertContains('documentation', $ids);

    foreach ($result['data']['templates'] as $template) {
      $this->assertArrayHasKey('id', $template);
      $this->assertArrayHasKey('label', $template);
      $this->assertArrayHasKey('description', $template);
      $this->assertArrayHasKey('category', $template);
      $this->assertArrayHasKey('component_summary', $template);
    }
  }

  /**
   * @covers ::getTemplate
   */
  public function testGetTemplateReturnsNotFound(): void {
    $service = new TemplateService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $this->createMock(AccessManager::class),
      $this->auditLogger,
    );

    $result = $service->getTemplate('does_not_exist');

    $this->assertFalse($result['success']);
    $this->assertSame('TEMPLATE_NOT_FOUND', $result['code']);
    $this->assertContains('blog', $result['available_templates']);
  }

  /**
   * @covers ::applyTemplate
   */
  public function testApplyTemplateRequiresAdminScope(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(FALSE);

    $service = new TemplateService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $accessManager,
      $this->auditLogger,
    );

    $result = $service->applyTemplate('blog');

    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

  /**
   * @covers ::previewTemplate
   */
  public function testPreviewTemplateShowsCreatesWhenNothingExists(): void {
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->willReturn(NULL);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn(NULL);

    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('load')->willReturn(NULL);

    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->method('load')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node_type', $nodeTypeStorage],
      ['taxonomy_vocabulary', $vocabStorage],
      ['user_role', $roleStorage],
      ['view', $viewStorage],
    ]);

    $service = new TemplateService(
      $entityTypeManager,
      $this->configFactory,
      $this->createMock(AccessManager::class),
      $this->auditLogger,
    );

    $result = $service->previewTemplate('blog');

    $this->assertTrue($result['success']);
    $this->assertSame('blog', $result['data']['template_id']);
    $this->assertNotEmpty($result['data']['will_create']);
    $this->assertEmpty($result['data']['will_skip']);
    $this->assertEmpty($result['data']['conflicts']);

    $idsByType = [];
    foreach ($result['data']['will_create'] as $item) {
      $idsByType[$item['type']][] = $item['id'];
    }

    $this->assertContains('article', $idsByType['content_type']);
    $this->assertContains('tags', $idsByType['vocabulary']);
    $this->assertContains('categories', $idsByType['vocabulary']);
    $this->assertContains('author', $idsByType['role']);
    $this->assertContains('recent_articles', $idsByType['view']);

    $article = array_values(array_filter(
      $result['data']['will_create'],
      static fn(array $item): bool => $item['type'] === 'content_type' && $item['id'] === 'article'
    ))[0] ?? NULL;
    $this->assertNotNull($article);
    $this->assertArrayHasKey('fields', $article);
    $this->assertContains('field_image', $article['fields']);
  }

  /**
   * @covers ::previewTemplate
   */
  public function testPreviewTemplateReportsMediaConflictWhenStorageUnavailable(): void {
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->willReturn(NULL);

    $vocabStorage = $this->createMock(EntityStorageInterface::class);
    $vocabStorage->method('load')->willReturn(NULL);

    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->method('load')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(
      static function (string $entityTypeId) use ($nodeTypeStorage, $vocabStorage, $viewStorage): EntityStorageInterface {
        return match ($entityTypeId) {
          'node_type' => $nodeTypeStorage,
          'taxonomy_vocabulary' => $vocabStorage,
          'view' => $viewStorage,
          'media_type' => throw new \Exception('Media module not installed'),
          default => throw new \InvalidArgumentException("Unexpected storage: $entityTypeId"),
        };
      }
    );

    $service = new TemplateService(
      $entityTypeManager,
      $this->configFactory,
      $this->createMock(AccessManager::class),
      $this->auditLogger,
    );

    $result = $service->previewTemplate('portfolio');

    $this->assertTrue($result['success']);

    $conflicts = $result['data']['conflicts'];
    $this->assertNotEmpty($conflicts);
    $this->assertTrue(
      (bool) array_filter(
        $conflicts,
        static fn(array $conflict): bool => ($conflict['type'] ?? '') === 'media_type'
          && ($conflict['reason'] ?? '') === 'Media module not installed'
      ),
      'Expected a media_type conflict when media_type storage is unavailable.'
    );
  }

}

