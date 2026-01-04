<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools\Service\McpToolCallContext
 * @group mcp_tools
 */
final class McpToolCallContextTest extends UnitTestCase {

  public function testContextTracksNestedExecution(): void {
    $context = new McpToolCallContext();
    $this->assertFalse($context->isActive());

    $context->enter();
    $this->assertTrue($context->isActive());

    $context->enter();
    $this->assertTrue($context->isActive());

    $context->leave();
    $this->assertTrue($context->isActive());

    $context->leave();
    $this->assertFalse($context->isActive());

    // Extra leaves should not underflow.
    $context->leave();
    $this->assertFalse($context->isActive());
  }

}

