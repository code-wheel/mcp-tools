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

}
