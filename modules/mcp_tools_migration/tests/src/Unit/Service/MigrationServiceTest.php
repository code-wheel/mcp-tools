<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools_migration\Service\MigrationService
 * @group mcp_tools_migration
 */
final class MigrationServiceTest extends UnitTestCase {

  private array $stateStorage = [];
  private StateInterface $state;

  protected function setUp(): void {
    parent::setUp();

    $this->stateStorage = [];
    $this->state = $this->createMock(StateInterface::class);
    $this->state->method('get')->willReturnCallback(function (string $key, mixed $default = NULL): mixed {
      return $this->stateStorage[$key] ?? $default;
    });
    $this->state->method('set')->willReturnCallback(function (string $key, mixed $value): void {
      $this->stateStorage[$key] = $value;
    });
  }

  private function createService(): MigrationService {
    return new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->state,
      $this->createMock(AccessManager::class),
      $this->createMock(AuditLogger::class),
    ) extends MigrationService {

      public function escape(string $value): string {
        return $this->csvEscape($value);
      }

      public function protectedField(string $name): bool {
        return $this->isProtectedField($name);
      }

    };
  }

  private function createMigrationService(array $overrides = []): MigrationService {
    return new MigrationService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['entity_field_manager'] ?? $this->createMock(EntityFieldManagerInterface::class),
      $overrides['current_user'] ?? $this->createMock(AccountProxyInterface::class),
      $overrides['state'] ?? $this->state,
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  /**
   * @covers ::importFromCsv
   */
  public function testImportFromCsvParsesRowsAndDelegatesToImportFromJson(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->state,
      $accessManager,
      $this->createMock(AuditLogger::class),
    ) extends MigrationService {
      public array $captured = [];

      public function importFromJson(string $contentType, array $items): array {
        $this->captured = $items;
        return ['success' => TRUE, 'data' => ['ok' => TRUE]];
      }
    };

    $csv = "title,body\nHello,\"A,B\"\n\n";
    $result = $service->importFromCsv('article', $csv, ['body' => 'body']);
    $this->assertTrue($result['success']);

    $this->assertSame([['title' => 'Hello', 'body' => 'A,B']], $service->captured);
  }

  /**
   * @covers ::importFromJson
   */
  public function testImportFromJsonReturnsValidationErrors(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node_type')->willReturn($nodeTypeStorage);

    $service = new class(
      $entityTypeManager,
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->state,
      $accessManager,
      $this->createMock(AuditLogger::class),
    ) extends MigrationService {
      public function validateImport(string $contentType, array $items): array {
        return [
          'success' => TRUE,
          'data' => [
            'errors' => [['row' => 1, 'field' => 'title', 'message' => 'missing']],
          ],
        ];
      }
    };

    $result = $service->importFromJson('article', [['body' => 'x']]);
    $this->assertFalse($result['success']);
    $this->assertNotEmpty($result['validation_errors']);
  }

  /**
   * @covers ::importFromJson
   * @covers ::setImportStatus
   * @covers ::getImportStatus
   */
  public function testImportFromJsonCreatesNodesAndUpdatesImportStatus(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());

    $node = new class() {
      public function save(): void {}
      public function id(): int { return 100; }
    };

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects($this->once())
      ->method('create')
      ->with($this->callback(static function (array $values): bool {
        return $values['type'] === 'article'
          && $values['title'] === 'Imported'
          && $values['uid'] === 42
          && !array_key_exists('uid_override', $values);
      }))
      ->willReturn($node);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node_type', $nodeTypeStorage],
      ['node', $nodeStorage],
    ]);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->with('node', 'article')->willReturn([]);

    $auditLogger = $this->createMock(AuditLogger::class);
    $auditLogger->expects($this->once())->method('logSuccess');

    $service = new class(
      $entityTypeManager,
      $entityFieldManager,
      $currentUser,
      $this->state,
      $accessManager,
      $auditLogger,
    ) extends MigrationService {
      public function validateImport(string $contentType, array $items): array {
        return ['success' => TRUE, 'data' => ['errors' => []]];
      }
    };

    $result = $service->importFromJson('article', [['title' => 'Imported', 'uid' => 1]]);
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['created_count']);

    $status = $service->getImportStatus();
    $this->assertTrue($status['success']);
    $this->assertTrue($status['data']['has_import']);
    $this->assertSame('completed', $status['data']['status']);
  }

  /**
   * @covers ::validateImport
   * @covers ::getFieldMapping
   */
  public function testValidateImportReportsMissingTitleAndUnknownFields(): void {
    $nodeType = new class() {
      public function label(): string { return 'Article'; }
    };

    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn($nodeType);

    $requiredDefinition = $this->createMock(FieldDefinitionInterface::class);
    $requiredDefinition->method('getLabel')->willReturn('Required');
    $requiredDefinition->method('getType')->willReturn('string');
    $requiredDefinition->method('getDescription')->willReturn('');
    $requiredDefinition->method('getSettings')->willReturn([]);
    $requiredDefinition->method('isRequired')->willReturn(TRUE);

    $optionalDefinition = $this->createMock(FieldDefinitionInterface::class);
    $optionalDefinition->method('getLabel')->willReturn('Optional');
    $optionalDefinition->method('getType')->willReturn('string');
    $optionalDefinition->method('getDescription')->willReturn('');
    $optionalDefinition->method('getSettings')->willReturn([]);
    $optionalDefinition->method('isRequired')->willReturn(FALSE);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->with('node', 'article')->willReturn([
      'field_required' => $requiredDefinition,
      'field_optional' => $optionalDefinition,
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node_type')->willReturn($nodeTypeStorage);

    $service = $this->createMigrationService([
      'entity_type_manager' => $entityTypeManager,
      'entity_field_manager' => $entityFieldManager,
    ]);

    $result = $service->validateImport('article', [['field_unknown' => 'x']]);
    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['valid']);
    $this->assertSame(1, $result['data']['error_count']);
    $this->assertGreaterThan(0, $result['data']['warning_count']);
  }

  /**
   * @covers ::exportToCsv
   * @covers ::exportToJson
   * @covers ::extractFieldValue
   */
  public function testExportFormatsIncludeCustomFields(): void {
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->with('node', 'article')->willReturn([
      'body' => $this->createMock(FieldDefinitionInterface::class),
      'field_tags' => $this->createMock(FieldDefinitionInterface::class),
    ]);

    $node = new class() {
      public function id(): int { return 1; }
      public function uuid(): string { return 'uuid-1'; }
      public function getTitle(): string { return 'Hello, "world"'; }
      public function isPublished(): bool { return TRUE; }
      public function getCreatedTime(): int { return 1700000000; }
      public function getChangedTime(): int { return 1700000100; }
      public function hasField(string $name): bool { return in_array($name, ['body', 'field_tags'], TRUE); }
      public function get(string $name): object {
        return match ($name) {
          'body' => new FakeExportFieldItemList('text_long', [new FakeExportFieldItem(['value' => 'Body'])]),
          'field_tags' => new FakeExportFieldItemList('entity_reference', [new FakeExportFieldItem(['target_id' => 123])]),
          default => new FakeExportFieldItemList('string', []),
        };
      }
    };

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('loadByProperties')->with(['type' => 'article'])->willReturn([$node]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node_type', $nodeTypeStorage],
      ['node', $nodeStorage],
    ]);

    $service = $this->createMigrationService([
      'entity_type_manager' => $entityTypeManager,
      'entity_field_manager' => $entityFieldManager,
    ]);

    $csv = $service->exportToCsv('article', 5);
    $this->assertTrue($csv['success']);
    $this->assertStringContainsString('field_tags', $csv['data']['csv_data']);
    $this->assertStringContainsString('"Hello, ""world"""', $csv['data']['csv_data']);

    $json = $service->exportToJson('article', 5);
    $this->assertTrue($json['success']);
    $this->assertSame(123, $json['data']['items'][0]['field_tags']['target_id']);
  }

  /**
   * @covers ::csvEscape
   */
  public function testCsvEscapeQuotesAndEscapesValues(): void {
    $service = $this->createService();

    $this->assertSame('plain', $service->escape('plain'));
    $this->assertSame('""""', $service->escape('"'));
    $this->assertSame('"a""b"', $service->escape('a"b'));
    $this->assertSame('"a,b"', $service->escape('a,b'));
  }

  /**
   * @covers ::isProtectedField
   * @covers ::getProtectedFields
   */
  public function testProtectedFieldBlocklistAndPatterns(): void {
    $service = $this->createService();

    $this->assertTrue($service->protectedField('uid'));
    $this->assertTrue($service->protectedField('revision_uid'));
    $this->assertTrue($service->protectedField('content_translation_source'));
    $this->assertFalse($service->protectedField('title'));

    $protected = $service->getProtectedFields();
    $this->assertArrayHasKey('fields', $protected);
    $this->assertArrayHasKey('patterns', $protected);
  }

}

final class FakeExportFieldDefinition {

  public function __construct(private readonly string $type) {}

  public function getType(): string {
    return $this->type;
  }

}

final class FakeExportFieldItemList implements \IteratorAggregate {

  /**
   * @param FakeExportFieldItem[] $items
   */
  public function __construct(private readonly string $type, private readonly array $items) {}

  public function isEmpty(): bool {
    return $this->items === [];
  }

  public function first(): ?FakeExportFieldItem {
    return $this->items[0] ?? NULL;
  }

  public function getFieldDefinition(): FakeExportFieldDefinition {
    return new FakeExportFieldDefinition($this->type);
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->items);
  }

}

final class FakeExportFieldItem {

  /**
   * @param array<string, mixed> $values
   */
  public function __construct(private readonly array $values) {}

  public function __get(string $name): mixed {
    return $this->values[$name] ?? NULL;
  }

  /**
   * @return array<string, mixed>
   */
  public function getValue(): array {
    return $this->values;
  }

}
