<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_templates\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\mcp_tools_templates\Service\ComponentFactory;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\RoleInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for ComponentFactory.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_templates\Service\ComponentFactory::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_templates')]
final class ComponentFactoryTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private LoggerInterface $logger;
  private ComponentFactory $factory;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->factory = new ComponentFactory(
      $this->entityTypeManager,
      $this->logger,
    );
  }

  public function testCreateVocabulariesEmpty(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($storage);

    $result = $this->factory->createVocabularies([], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertEmpty($result['skipped']);
    $this->assertEmpty($result['errors']);
  }

  public function testCreateVocabulariesCreatesNew(): void {
    $vocabulary = $this->createMock(VocabularyInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($vocabulary);

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($storage);

    $result = $this->factory->createVocabularies([
      'tags' => ['label' => 'Tags', 'description' => 'Free tagging'],
    ], TRUE);

    $this->assertCount(1, $result['created']);
    $this->assertSame('vocabulary', $result['created'][0]['type']);
    $this->assertSame('tags', $result['created'][0]['id']);
  }

  public function testCreateVocabulariesSkipsExisting(): void {
    $existing = $this->createMock(VocabularyInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($existing);

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($storage);

    $result = $this->factory->createVocabularies([
      'tags' => ['label' => 'Tags'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['skipped']);
    $this->assertSame('tags', $result['skipped'][0]['id']);
  }

  public function testCreateVocabulariesHandlesException(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willThrowException(new \Exception('Database error'));

    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_vocabulary')
      ->willReturn($storage);

    $result = $this->factory->createVocabularies([
      'tags' => ['label' => 'Tags'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['errors']);
    $this->assertStringContainsString('Database error', $result['errors'][0]['error']);
  }

  public function testCreateRolesCreatesNew(): void {
    $role = $this->createMock(RoleInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($role);

    $this->entityTypeManager->method('getStorage')
      ->with('user_role')
      ->willReturn($storage);

    $result = $this->factory->createRoles([
      'editor' => [
        'label' => 'Editor',
        'permissions' => ['access content', 'create article content'],
      ],
    ], TRUE);

    $this->assertCount(1, $result['created']);
    $this->assertSame('role', $result['created'][0]['type']);
    $this->assertSame('editor', $result['created'][0]['id']);
  }

  public function testCreateRolesSkipsExisting(): void {
    $existing = $this->createMock(RoleInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($existing);

    $this->entityTypeManager->method('getStorage')
      ->with('user_role')
      ->willReturn($storage);

    $result = $this->factory->createRoles([
      'editor' => ['label' => 'Editor'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['skipped']);
  }

  public function testCreateContentTypesCreatesNew(): void {
    $nodeType = $this->getMockBuilder('\\Drupal\\node\\Entity\\NodeType')
      ->disableOriginalConstructor()
      ->getMock();

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->willReturn(NULL);
    $nodeTypeStorage->method('create')->willReturn($nodeType);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($type) use ($nodeTypeStorage) {
        if ($type === 'node_type') {
          return $nodeTypeStorage;
        }
        return $this->createMock(EntityStorageInterface::class);
      });

    $result = $this->factory->createContentTypes([
      'blog' => ['label' => 'Blog Post', 'description' => 'Blog content'],
    ], TRUE);

    $this->assertCount(1, $result['created']);
    $this->assertSame('content_type', $result['created'][0]['type']);
    $this->assertSame('blog', $result['created'][0]['id']);
  }

  public function testCreateContentTypesSkipsExisting(): void {
    $existing = $this->getMockBuilder('\\Drupal\\node\\Entity\\NodeType')
      ->disableOriginalConstructor()
      ->getMock();

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->willReturn($existing);

    $this->entityTypeManager->method('getStorage')
      ->with('node_type')
      ->willReturn($nodeTypeStorage);

    $result = $this->factory->createContentTypes([
      'blog' => ['label' => 'Blog Post'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['skipped']);
  }

  public function testCreateMediaTypesModuleNotInstalled(): void {
    $this->entityTypeManager->method('getStorage')
      ->with('media_type')
      ->willThrowException(new \Exception('Module not installed'));

    $result = $this->factory->createMediaTypes([
      'document' => ['label' => 'Document'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['errors']);
    $this->assertStringContainsString('Media module not installed', $result['errors'][0]['error']);
  }

  public function testCreateMediaTypesCreatesNew(): void {
    $mediaType = $this->getMockBuilder('\\Drupal\\media\\Entity\\MediaType')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($mediaType);

    $this->entityTypeManager->method('getStorage')
      ->with('media_type')
      ->willReturn($storage);

    $result = $this->factory->createMediaTypes([
      'document' => ['label' => 'Document', 'source' => 'file'],
    ], TRUE);

    $this->assertCount(1, $result['created']);
    $this->assertSame('media_type', $result['created'][0]['type']);
  }

  public function testCreateWebformsModuleNotInstalled(): void {
    $this->entityTypeManager->method('getStorage')
      ->with('webform')
      ->willThrowException(new \Exception('Module not installed'));

    $result = $this->factory->createWebforms([
      'contact' => ['label' => 'Contact Form'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['errors']);
    $this->assertStringContainsString('Webform module not installed', $result['errors'][0]['error']);
  }

  public function testCreateViewsCreatesNew(): void {
    $view = $this->getMockBuilder('\\Drupal\\views\\Entity\\View')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($view);

    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($storage);

    $result = $this->factory->createViews([
      'content_list' => [
        'label' => 'Content List',
        'description' => 'Lists content',
        'pager' => 25,
        'style' => 'table',
      ],
    ], TRUE);

    $this->assertCount(1, $result['created']);
    $this->assertSame('view', $result['created'][0]['type']);
    $this->assertSame('content_list', $result['created'][0]['id']);
  }

  public function testCreateViewsWithPageDisplay(): void {
    $view = $this->getMockBuilder('\\Drupal\\views\\Entity\\View')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($view);

    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($storage);

    $result = $this->factory->createViews([
      'content_list' => [
        'label' => 'Content List',
        'display' => ['page' => '/content-list'],
      ],
    ], TRUE);

    $this->assertCount(1, $result['created']);
  }

  public function testCreateViewsWithBlockDisplay(): void {
    $view = $this->getMockBuilder('\\Drupal\\views\\Entity\\View')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($view);

    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($storage);

    $result = $this->factory->createViews([
      'content_list' => [
        'label' => 'Content List',
        'display' => ['block' => TRUE],
      ],
    ], TRUE);

    $this->assertCount(1, $result['created']);
  }

  public function testCreateViewsSkipsExisting(): void {
    $existing = $this->getMockBuilder('\\Drupal\\views\\Entity\\View')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($existing);

    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($storage);

    $result = $this->factory->createViews([
      'content_list' => ['label' => 'Content List'],
    ], TRUE);

    $this->assertEmpty($result['created']);
    $this->assertCount(1, $result['skipped']);
  }

  public function testConfigureFieldDisplayLogsWarningOnException(): void {
    $formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $formDisplayStorage->method('load')->willReturn(NULL);
    $formDisplayStorage->method('create')
      ->willThrowException(new \Exception('Display configuration error'));

    $this->entityTypeManager->method('getStorage')
      ->with('entity_form_display')
      ->willReturn($formDisplayStorage);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'Failed to configure display for field @field: @error',
        $this->anything()
      );

    $this->factory->configureFieldDisplay('node', 'article', 'field_test', [
      'type' => 'string',
      'label' => 'Test Field',
    ]);
  }

}
