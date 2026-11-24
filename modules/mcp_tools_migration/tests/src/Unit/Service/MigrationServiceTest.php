<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_migration\Service\MigrateImport;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_migration\Service\MigrationService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_migration')]
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
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testImportFromCsvParsesRowsAndDelegatesToImportFromJson(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $accessManager,
      $this->createMock(AuditLogger::class),
    ) extends MigrationService {
      public array $captured = [];

      public array $capturedErrors = [];

      protected function import(string $contentType, array $items, string $key, array $options = [], array $rowErrors = []): array {
        $this->captured = $items;
        $this->capturedErrors = $rowErrors;
        return ['success' => TRUE, 'data' => ['ok' => TRUE]];
      }
    };

    $csv = "id,title,body\n1,Hello,\"A,B\"\n\n2,\"Two\nlines\",x\n3,short\n";
    $result = $service->importFromCsv('article', $csv, ['body' => 'body'], 'id');
    $this->assertTrue($result['success']);

    // Rows by their number; a quoted comma and a quoted newline are values.
    $this->assertSame([
      1 => ['id' => '1', 'title' => 'Hello', 'body' => 'A,B'],
      2 => ['id' => '2', 'title' => "Two\nlines", 'body' => 'x'],
    ], $service->captured);
    $this->assertSame([3], array_keys($service->capturedErrors), 'A short row was not reported.');
  }

  /**
   * A service with the mocks an import needs, and a recording migration.
   *
   * @return array{0: \Drupal\mcp_tools_migration\Service\MigrationService, 1: object}
   *   The service, and the record of what reached the migration.
   */
  private function importingService(): array {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());
    $nodeType = $this->createMock(EntityTypeInterface::class);
    $nodeType->method('getKey')->willReturnCallback(fn(string $key) => ['id' => 'nid', 'revision' => 'vid', 'bundle' => 'type', 'label' => 'title'][$key] ?? FALSE);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node_type')->willReturn($nodeTypeStorage);
    $entityTypeManager->method('getDefinition')->with('node')->willReturn($nodeType);
    $definition = function (string $type): FieldDefinitionInterface {
      $definition = $this->createMock(FieldDefinitionInterface::class);
      $definition->method('getType')->willReturn($type);
      return $definition;
    };
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->with('node', 'article')->willReturn([
      'title' => $definition('string'),
      'uid' => $definition('entity_reference'),
      'status' => $definition('boolean'),
      'body' => $definition('string_long'),
    ]);
    $record = new \stdClass();
    $record->rows = NULL;
    $record->fields = NULL;
    $migrate = $this->createMock(MigrateImport::class);
    // Migrate and migrate_plus are on.
    $migrate->method('ready')->willReturn(TRUE);
    $migrate->method('made')->willReturn([]);
    $migrate->method('save')->willReturnCallback(function (string $id, string $entityType, string $bundle, string $key, array $rows, array $fields) use ($record): void {
      $record->rows = $rows;
      $record->fields = $fields;
    });
    $migrate->method('run')->willReturnCallback(fn() => ['saved' => array_map('strval', array_keys($record->rows)), 'failed' => [], 'done' => TRUE, 'remaining' => 0, 'reset' => FALSE]);
    $migrate->method('exists')->willReturn(TRUE);
    $migrate->method('status')->willReturnCallback(fn(string $id) => ['import_id' => $id, 'imported' => count((array) $record->rows)]);
    $service = new MigrationService($entityTypeManager, $entityFieldManager, $accessManager, $this->createMock(AuditLogger::class), $migrate);
    return [$service, $record];
  }

  public function testImportFromJsonReturnsValidationErrors(): void {
    [$service, $record] = $this->importingService();

    $result = $service->importFromJson('article', [['id' => '1', 'body' => 'x'], ['id' => '2', 'title' => 'Fine']], 'id');
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['failed_count']);
    $this->assertSame(1, $result['data']['failed'][0]['row']);
    $this->assertStringContainsString('Title', $result['data']['failed'][0]['error']);
    $this->assertSame(['2'], array_map('strval', array_keys($record->rows)), 'A row without a title reached the migration.');
  }

  public function testImportFromJsonCreatesNodesAndUpdatesImportStatus(): void {
    [$service, $record] = $this->importingService();

    $result = $service->importFromJson('article', [['id' => '1', 'title' => 'Imported', 'uid' => 1]], 'id');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(1, $result['data']['created_count']);
    // The owner is never taken from a row.
    $this->assertSame(['uid'], $result['data']['ignored_columns']);
    $this->assertNotContains('uid', $record->fields);
    $this->assertArrayNotHasKey('uid', $record->rows['1']);
    $this->assertSame('Imported', $record->rows['1']['title']);

    $status = $service->getImportStatus($result['data']['import_id']);
    $this->assertTrue($status['success']);
    $this->assertTrue($status['data']['has_import']);
    $this->assertSame(1, $status['data']['imported']);
  }

  public function testAnImportWithoutAKeyIsRefused(): void {
    [$service, $record] = $this->importingService();
    $result = $service->importFromJson('article', [['title' => 'One']]);
    $this->assertFalse($result['success']);
    $this->assertSame('KEY_REQUIRED', $result['code']);
    $this->assertNull($record->rows, 'An import without a key reached the migration.');
  }

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
    // An entity query of the type in id order: a count, then a page.
    $nodeStorage->method('getQuery')->willReturnCallback(function () {
      $query = $this->createMock(QueryInterface::class);
      $counting = FALSE;
      foreach (['accessCheck', 'condition', 'sort', 'range'] as $method) {
        $query->method($method)->willReturnSelf();
      }
      $query->method('count')->willReturnCallback(function () use ($query, &$counting) {
        $counting = TRUE;
        return $query;
      });
      $query->method('execute')->willReturnCallback(function () use (&$counting) {
        return $counting ? 1 : [1 => 1];
      });
      return $query;
    });
    $nodeStorage->method('loadMultiple')->with([1 => 1])->willReturn([1 => $node]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node_type', $nodeTypeStorage],
      ['node', $nodeStorage],
    ]);
    $nodeInfo = $this->createMock(EntityTypeInterface::class);
    $nodeInfo->method('getKey')->willReturnCallback(fn(string $key) => ['id' => 'nid', 'bundle' => 'type', 'label' => 'title'][$key] ?? FALSE);
    $nodeInfo->method('getBundleEntityType')->willReturn('node_type');
    $entityTypeManager->method('getDefinition')->with('node')->willReturn($nodeInfo);

    $service = $this->createMigrationService([
      'entity_type_manager' => $entityTypeManager,
      'entity_field_manager' => $entityFieldManager,
    ]);

    $csv = $service->exportToCsv('article', 5);
    $this->assertTrue($csv['success']);
    $this->assertStringContainsString('field_tags', $csv['data']['csv_data']);
    $this->assertStringContainsString('"Hello, ""world"""', $csv['data']['csv_data']);
    // The tag as the CSV import reads it: its id (several joined by |).
    $this->assertStringContainsString(',123', $csv['data']['csv_data']);

    $json = $service->exportToJson('article', 5);
    $this->assertTrue($json['success']);
    $this->assertSame(123, $json['data']['items'][0]['field_tags']['target_id']);
    $this->assertSame('uuid-1', $json['data']['items'][0]['uuid']);
    $this->assertSame(1, $json['data']['total']);
    $this->assertNull($json['data']['next_offset']);
    $this->assertStringContainsString('uuid-1', $csv['data']['csv_data']);
  }

  public function testCsvEscapeQuotesAndEscapesValues(): void {
    $service = $this->createService();

    $this->assertSame('plain', $service->escape('plain'));
    $this->assertSame('""""', $service->escape('"'));
    $this->assertSame('"a""b"', $service->escape('a"b'));
    $this->assertSame('"a,b"', $service->escape('a,b'));
  }

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

  public function getValue(): array {
    return array_map(fn($item) => $item->getValue(), $this->items);
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
