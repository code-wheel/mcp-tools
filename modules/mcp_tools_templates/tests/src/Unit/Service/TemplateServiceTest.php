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
   * @covers ::applyTemplate
   */
  public function testApplyTemplateRespectsComponentFilterAndLogsSuccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(TRUE);

    $auditLogger = $this->createMock(AuditLogger::class);
    $auditLogger->expects($this->once())
      ->method('log')
      ->with(
        'apply_template',
        'template',
        'blog',
        $this->callback(static function (array $details): bool {
          return ($details['template'] ?? NULL) === 'blog'
            && ($details['status'] ?? NULL) === 'success'
            && ($details['error_count'] ?? NULL) === 0;
        }),
        TRUE,
      );

    $service = new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $accessManager,
      $auditLogger,
    ) extends TemplateService {
      public array $called = [];

      protected function createVocabularies(array $vocabularies, bool $skipExisting): array {
        $this->called[] = 'vocabularies';
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }

      protected function createRoles(array $roles, bool $skipExisting): array {
        $this->called[] = 'roles';
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }

      protected function createContentTypes(array $contentTypes, bool $skipExisting): array {
        $this->called[] = 'content_types';
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }

      protected function createViews(array $views, bool $skipExisting): array {
        $this->called[] = 'views';
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }
    };

    $result = $service->applyTemplate('blog', [
      'components' => ['vocabularies', 'roles'],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame(['vocabularies', 'roles'], $service->called);
  }

  /**
   * @covers ::applyTemplate
   */
  public function testApplyTemplateLogsFailureWhenErrorsOccur(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(TRUE);

    $auditLogger = $this->createMock(AuditLogger::class);
    $auditLogger->expects($this->once())
      ->method('log')
      ->with(
        'apply_template',
        'template',
        'blog',
        $this->callback(static function (array $details): bool {
          return ($details['template'] ?? NULL) === 'blog'
            && ($details['status'] ?? NULL) === 'partial'
            && ($details['error_count'] ?? NULL) === 1;
        }),
        FALSE,
      );

    $service = new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $accessManager,
      $auditLogger,
    ) extends TemplateService {

      protected function createVocabularies(array $vocabularies, bool $skipExisting): array {
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }

      protected function createRoles(array $roles, bool $skipExisting): array {
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }

      protected function createContentTypes(array $contentTypes, bool $skipExisting): array {
        return ['created' => [], 'skipped' => [], 'errors' => [['type' => 'content_type', 'id' => 'article', 'error' => 'boom']]];
      }

      protected function createViews(array $views, bool $skipExisting): array {
        return ['created' => [], 'skipped' => [], 'errors' => []];
      }
    };

    $result = $service->applyTemplate('blog');

    $this->assertFalse($result['success']);
    $this->assertCount(1, $result['data']['errors']);
  }

  /**
   * @covers ::exportAsTemplate
   */
  public function testExportAsTemplateValidatesAdminScopeAndMachineName(): void {
    $deniedAccessManager = $this->createMock(AccessManager::class);
    $deniedAccessManager->method('canAdmin')->willReturn(FALSE);
    $deniedService = new TemplateService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $deniedAccessManager,
      $this->auditLogger,
    );

    $denied = $deniedService->exportAsTemplate('example', [], [], []);
    $this->assertFalse($denied['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $denied['code']);

    $allowedAccessManager = $this->createMock(AccessManager::class);
    $allowedAccessManager->method('canAdmin')->willReturn(TRUE);
    $allowedService = new TemplateService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $allowedAccessManager,
      $this->auditLogger,
    );

    $invalid = $allowedService->exportAsTemplate('Bad Name', [], [], []);
    $this->assertFalse($invalid['success']);
    $this->assertSame('INVALID_NAME', $invalid['code']);
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
