<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  private function createService(): MigrationService {
    return new class(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(StateInterface::class),
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
