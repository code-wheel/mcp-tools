<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_templates\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_templates\Service\ComponentFactory;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for TemplateService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_templates\Service\TemplateService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_templates')]
final class TemplateServiceTest extends UnitTestCase {

  private ConfigFactoryInterface $configFactory;
  private AuditLogger $auditLogger;
  private ComponentFactory $componentFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->componentFactory = $this->createMock(ComponentFactory::class);
  }

  private function createService(
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?AccessManager $accessManager = NULL,
    ?AuditLogger $auditLogger = NULL,
    ?ComponentFactory $componentFactory = NULL,
  ): TemplateService {
    return new TemplateService(
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
      $this->configFactory,
      $accessManager ?? $this->createMock(AccessManager::class),
      $auditLogger ?? $this->auditLogger,
      $componentFactory ?? $this->componentFactory,
    );
  }

  public function testListTemplatesReturnsExpectedBasics(): void {
    $service = $this->createService();

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

  public function testGetTemplateReturnsNotFound(): void {
    $service = $this->createService();

    $result = $service->getTemplate('does_not_exist');

    $this->assertFalse($result['success']);
    $this->assertSame('TEMPLATE_NOT_FOUND', $result['code']);
    $this->assertContains('blog', $result['available_templates']);
  }

  public function testApplyTemplateRequiresAdminScope(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(FALSE);

    $service = $this->createService(accessManager: $accessManager);

    $result = $service->applyTemplate('blog');

    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

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

    // Track which factory methods are called.
    $called = [];
    $componentFactory = $this->createMock(ComponentFactory::class);
    $componentFactory->method('createVocabularies')->willReturnCallback(function () use (&$called) {
      $called[] = 'vocabularies';
      return ['created' => [], 'skipped' => [], 'errors' => []];
    });
    $componentFactory->method('createRoles')->willReturnCallback(function () use (&$called) {
      $called[] = 'roles';
      return ['created' => [], 'skipped' => [], 'errors' => []];
    });
    $componentFactory->method('createContentTypes')->willReturnCallback(function () use (&$called) {
      $called[] = 'content_types';
      return ['created' => [], 'skipped' => [], 'errors' => []];
    });
    $componentFactory->method('createViews')->willReturnCallback(function () use (&$called) {
      $called[] = 'views';
      return ['created' => [], 'skipped' => [], 'errors' => []];
    });

    $service = $this->createService(
      accessManager: $accessManager,
      auditLogger: $auditLogger,
      componentFactory: $componentFactory,
    );

    $result = $service->applyTemplate('blog', [
      'components' => ['vocabularies', 'roles'],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame(['vocabularies', 'roles'], $called);
  }

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

    $componentFactory = $this->createMock(ComponentFactory::class);
    $componentFactory->method('createVocabularies')
      ->willReturn(['created' => [], 'skipped' => [], 'errors' => []]);
    $componentFactory->method('createRoles')
      ->willReturn(['created' => [], 'skipped' => [], 'errors' => []]);
    $componentFactory->method('createContentTypes')
      ->willReturn(['created' => [], 'skipped' => [], 'errors' => [['type' => 'content_type', 'id' => 'article', 'error' => 'boom']]]);
    $componentFactory->method('createViews')
      ->willReturn(['created' => [], 'skipped' => [], 'errors' => []]);

    $service = $this->createService(
      accessManager: $accessManager,
      auditLogger: $auditLogger,
      componentFactory: $componentFactory,
    );

    $result = $service->applyTemplate('blog');

    $this->assertFalse($result['success']);
    $this->assertCount(1, $result['data']['errors']);
  }

  public function testExportAsTemplateValidatesAdminScopeAndMachineName(): void {
    $deniedAccessManager = $this->createMock(AccessManager::class);
    $deniedAccessManager->method('canAdmin')->willReturn(FALSE);
    $deniedService = $this->createService(accessManager: $deniedAccessManager);

    $denied = $deniedService->exportAsTemplate('example', [], [], []);
    $this->assertFalse($denied['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $denied['code']);

    $allowedAccessManager = $this->createMock(AccessManager::class);
    $allowedAccessManager->method('canAdmin')->willReturn(TRUE);
    $allowedService = $this->createService(accessManager: $allowedAccessManager);

    $invalid = $allowedService->exportAsTemplate('Bad Name', [], [], []);
    $this->assertFalse($invalid['success']);
    $this->assertSame('INVALID_NAME', $invalid['code']);
  }

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

    $service = $this->createService(entityTypeManager: $entityTypeManager);

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

    $service = $this->createService(entityTypeManager: $entityTypeManager);

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
