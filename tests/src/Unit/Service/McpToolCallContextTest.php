<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\McpToolCallContext::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class McpToolCallContextTest extends UnitTestCase {

  public function testContextTracksNestedExecution(): void {
    $context = new McpToolCallContext();
    $this->assertFalse($context->isActive());
    $this->assertNull($context->getCorrelationId());

    $context->enter();
    $this->assertTrue($context->isActive());
    $correlationId = $context->getCorrelationId();
    $this->assertNotNull($correlationId);
    $this->assertSame($correlationId, $context->getCorrelationId());

    $context->enter();
    $this->assertTrue($context->isActive());
    $this->assertSame($correlationId, $context->getCorrelationId());

    $context->leave();
    $this->assertTrue($context->isActive());
    $this->assertSame($correlationId, $context->getCorrelationId());

    $context->leave();
    $this->assertFalse($context->isActive());
    $this->assertNull($context->getCorrelationId());

    // Extra leaves should not underflow.
    $context->leave();
    $this->assertFalse($context->isActive());
    $this->assertNull($context->getCorrelationId());
  }

  public function testMultipleEnterLeaveCyclesGenerateNewCorrelationIds(): void {
    $context = new McpToolCallContext();

    // First cycle.
    $context->enter();
    $firstCorrelationId = $context->getCorrelationId();
    $this->assertNotNull($firstCorrelationId);
    $context->leave();
    $this->assertNull($context->getCorrelationId());

    // Second cycle should generate a new correlation ID.
    $context->enter();
    $secondCorrelationId = $context->getCorrelationId();
    $this->assertNotNull($secondCorrelationId);
    $this->assertNotSame($firstCorrelationId, $secondCorrelationId);
    $context->leave();
  }

  public function testCorrelationIdFormatIsHex(): void {
    $context = new McpToolCallContext();

    $context->enter();
    $correlationId = $context->getCorrelationId();
    $this->assertNotNull($correlationId);
    // Should be 16 hex characters (8 bytes = 16 hex chars).
    $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $correlationId);
    $context->leave();
  }

  public function testLeaveDoesNothingWhenNotActive(): void {
    $context = new McpToolCallContext();

    // Multiple leaves when not active should be safe.
    $context->leave();
    $context->leave();
    $context->leave();

    $this->assertFalse($context->isActive());
    $this->assertNull($context->getCorrelationId());
  }

  public function testDeepNesting(): void {
    $context = new McpToolCallContext();

    // Deep nesting should work.
    for ($i = 0; $i < 10; $i++) {
      $context->enter();
    }
    $this->assertTrue($context->isActive());
    $correlationId = $context->getCorrelationId();

    // All leaves should maintain same correlation until last.
    for ($i = 0; $i < 9; $i++) {
      $context->leave();
      $this->assertTrue($context->isActive());
      $this->assertSame($correlationId, $context->getCorrelationId());
    }

    // Final leave should deactivate.
    $context->leave();
    $this->assertFalse($context->isActive());
    $this->assertNull($context->getCorrelationId());
  }

}
