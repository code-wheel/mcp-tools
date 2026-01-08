<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Trait\WriteAccessTrait;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Trait\WriteAccessTrait::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class WriteAccessTraitTest extends UnitTestCase {

  public function testCheckWriteAccessReturnsNullWhenAllowed(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->expects($this->once())
      ->method('canWrite')
      ->willReturn(TRUE);

    $tool = new class() {
      use WriteAccessTrait;

      public function publicCheck(): ?array {
        return $this->checkWriteAccess();
      }
    };

    $tool->setAccessManager($accessManager);
    $this->assertNull($tool->publicCheck());
  }

  public function testCheckWriteAccessReturnsDeniedPayload(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied.',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $tool = new class() {
      use WriteAccessTrait;

      public function publicCheck(): ?array {
        return $this->checkWriteAccess();
      }
    };

    $tool->setAccessManager($accessManager);
    $denied = $tool->publicCheck();
    $this->assertIsArray($denied);
    $this->assertFalse($denied['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $denied['code']);
  }

  public function testCheckAdminAccessReturnsNullWhenAllowed(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('checkWriteAccess')->with('admin', 'admin')->willReturn([
      'allowed' => TRUE,
      'reason' => NULL,
    ]);

    $tool = new class() {
      use WriteAccessTrait;

      public function publicCheck(): ?array {
        return $this->checkAdminAccess();
      }
    };

    $tool->setAccessManager($accessManager);
    $this->assertNull($tool->publicCheck());
  }

  public function testCheckAdminAccessReturnsStructuredErrorWhenDenied(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('checkWriteAccess')->with('admin', 'admin')->willReturn([
      'allowed' => FALSE,
      'reason' => 'Admin scope required.',
      'code' => 'INSUFFICIENT_SCOPE',
      'retry_after' => 12,
    ]);

    $tool = new class() {
      use WriteAccessTrait;

      public function publicCheck(): ?array {
        return $this->checkAdminAccess();
      }
    };

    $tool->setAccessManager($accessManager);
    $denied = $tool->publicCheck();
    $this->assertIsArray($denied);
    $this->assertFalse($denied['success']);
    $this->assertSame('Admin scope required.', $denied['error']);
    $this->assertSame('INSUFFICIENT_SCOPE', $denied['code']);
    $this->assertSame(12, $denied['retry_after']);
  }

}

