<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_batch\Service\BatchService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_batch')]
final class BatchServiceTest extends UnitTestCase {

  private function createService(): BatchService {
    return new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(AccessManager::class),
      $this->createMock(AuditLogger::class),
    ) extends BatchService {

      public function normalizeField(string $fieldName, mixed $value, array $definitions): mixed {
        return $this->normalizeFieldValue($fieldName, $value, $definitions);
      }

      public function normalizeDestination(string $destination): string {
        return $this->normalizeRedirectDestination($destination);
      }

    };
  }

  private function createBatchService(array $overrides = []): BatchService {
    return new BatchService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['entity_field_manager'] ?? $this->createMock(EntityFieldManagerInterface::class),
      $overrides['current_user'] ?? $this->createMock(AccountProxyInterface::class),
      $overrides['module_handler'] ?? $this->createMock(ModuleHandlerInterface::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testNormalizeFieldValueMapsKnownTypes(): void {
    $service = $this->createService();

    $text = $this->createMock(FieldDefinitionInterface::class);
    $text->method('getType')->willReturn('text_with_summary');

    $entityRef = $this->createMock(FieldDefinitionInterface::class);
    $entityRef->method('getType')->willReturn('entity_reference');

    $link = $this->createMock(FieldDefinitionInterface::class);
    $link->method('getType')->willReturn('link');

    $datetime = $this->createMock(FieldDefinitionInterface::class);
    $datetime->method('getType')->willReturn('datetime');

    $definitions = [
      'body' => $text,
      'field_tags' => $entityRef,
      'field_link' => $link,
      'field_date' => $datetime,
    ];

    $this->assertSame(
      ['value' => 'Hello', 'format' => 'basic_html'],
      $service->normalizeField('body', 'Hello', $definitions),
    );

    $this->assertSame(
      ['target_id' => 123],
      $service->normalizeField('field_tags', 123, $definitions),
    );

    $this->assertSame(
      ['uri' => 'https://example.com'],
      $service->normalizeField('field_link', 'https://example.com', $definitions),
    );

    $this->assertSame(
      ['value' => '2026-01-01T00:00:00'],
      $service->normalizeField('field_date', '2026-01-01T00:00:00', $definitions),
    );

    // If definitions are missing, return the original value.
    $this->assertSame('x', $service->normalizeField('field_missing', 'x', $definitions));
  }

  public function testNormalizeRedirectDestinationProducesInternalUris(): void {
    $service = $this->createService();

    $this->assertSame('https://example.com', $service->normalizeDestination('https://example.com'));
    $this->assertSame('internal:/foo', $service->normalizeDestination('/foo'));
    $this->assertSame('internal:/foo/bar', $service->normalizeDestination('foo/bar'));
    $this->assertSame('entity:node/1', $service->normalizeDestination('entity:node/1'));
    $this->assertSame('route:<front>', $service->normalizeDestination('route:<front>'));
  }

  public function testCreateMultipleContentCreatesItemsViaStorage(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(99);

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());

    $node = new class() {
      public function save(): void {}
      public function id(): int { return 123; }
      public function uuid(): string { return 'uuid-123'; }
      public function toUrl(): object {
        return new class() { public function toString(): string { return '/node/123'; } };
      }
    };

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects($this->once())
      ->method('create')
      ->with($this->callback(static function (array $values): bool {
        return $values['type'] === 'article'
          && $values['title'] === 'Hello'
          && $values['uid'] === 99
          && $values['body'] === ['value' => 'Body', 'format' => 'basic_html']
          && $values['field_tags'] === ['target_id' => 123]
          && $values['field_link'] === ['uri' => 'https://example.com'];
      }))
      ->willReturn($node);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node_type', $nodeTypeStorage],
      ['node', $nodeStorage],
    ]);

    $text = $this->createMock(FieldDefinitionInterface::class);
    $text->method('getType')->willReturn('text_with_summary');
    $entityRef = $this->createMock(FieldDefinitionInterface::class);
    $entityRef->method('getType')->willReturn('entity_reference');
    $link = $this->createMock(FieldDefinitionInterface::class);
    $link->method('getType')->willReturn('link');

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->with('node', 'article')->willReturn([
      'body' => $text,
      'field_tags' => $entityRef,
      'field_link' => $link,
    ]);

    $auditLogger = $this->createMock(AuditLogger::class);
    $auditLogger->expects($this->once())->method('logSuccess');

    $service = $this->createBatchService([
      'entity_type_manager' => $entityTypeManager,
      'entity_field_manager' => $entityFieldManager,
      'current_user' => $currentUser,
      'access_manager' => $accessManager,
      'audit_logger' => $auditLogger,
    ]);

    $result = $service->createMultipleContent('article', [
      [
        'title' => 'Hello',
        'fields' => [
          'body' => 'Body',
          'tags' => 123,
          'link' => 'https://example.com',
        ],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['created_count']);
    $this->assertSame('/node/123', $result['data']['created'][0]['url']);
  }

  public function testCreateMultipleRedirectsFailsWhenRedirectModuleMissing(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('redirect')->willReturn(FALSE);

    $service = $this->createBatchService([
      'module_handler' => $moduleHandler,
      'access_manager' => $accessManager,
    ]);

    $result = $service->createMultipleRedirects([['source' => '/a', 'destination' => '/b']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Redirect module', $result['error']);
  }

}
