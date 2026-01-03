<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools_batch\Service\BatchService
 * @group mcp_tools_batch
 */
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

  /**
   * @covers ::normalizeFieldValue
   */
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

  /**
   * @covers ::normalizeRedirectDestination
   */
  public function testNormalizeRedirectDestinationProducesInternalUris(): void {
    $service = $this->createService();

    $this->assertSame('https://example.com', $service->normalizeDestination('https://example.com'));
    $this->assertSame('internal:/foo', $service->normalizeDestination('/foo'));
    $this->assertSame('internal:/foo/bar', $service->normalizeDestination('foo/bar'));
    $this->assertSame('entity:node/1', $service->normalizeDestination('entity:node/1'));
    $this->assertSame('route:<front>', $service->normalizeDestination('route:<front>'));
  }

}

