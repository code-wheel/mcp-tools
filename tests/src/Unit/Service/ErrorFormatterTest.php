<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\mcp_tools\Service\ErrorFormatter;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mcp_tools\Service\ErrorFormatter
 * @group mcp_tools
 */
final class ErrorFormatterTest extends UnitTestCase {

  private ErrorFormatter $formatter;

  protected function setUp(): void {
    parent::setUp();
    $this->formatter = new ErrorFormatter();
  }

  /**
   * @covers ::notFound
   */
  public function testNotFoundIncludesSuggestionWhenProvided(): void {
    $result = $this->formatter->notFound('user', '99', 'Try listing users first.');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_NOT_FOUND, $result['code']);
    $this->assertStringContainsString("The user '99' was not found.", $result['error']);
    $this->assertStringContainsString('Try listing users first.', $result['error']);
    $this->assertSame('user', $result['details']['entity_type']);
    $this->assertSame('99', $result['details']['identifier']);
  }

  /**
   * @covers ::validation
   */
  public function testValidationDoesNotEchoSensitiveValues(): void {
    $result = $this->formatter->validation('password', 'too short', 'secret');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_VALIDATION, $result['code']);
    $this->assertArrayHasKey('details', $result);
    $this->assertSame('password', $result['details']['field']);
    $this->assertArrayNotHasKey('value', $result['details']);
  }

  /**
   * @covers ::validation
   */
  public function testValidationIncludesValueForNonSensitiveFields(): void {
    $result = $this->formatter->validation('title', 'required', '');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_VALIDATION, $result['code']);
    $this->assertSame('', $result['details']['value']);
  }

  /**
   * @covers ::success
   */
  public function testSuccessMergesData(): void {
    $result = $this->formatter->success('OK', ['data' => ['x' => 1]]);

    $this->assertTrue($result['success']);
    $this->assertSame('OK', $result['message']);
    $this->assertSame(['x' => 1], $result['data']);
  }

  /**
   * @covers ::insufficientScope
   */
  public function testInsufficientScopeIncludesScopesInDetails(): void {
    $result = $this->formatter->insufficientScope('admin', ['read', 'write']);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_SCOPE, $result['code']);
    $this->assertSame('admin', $result['details']['required_scope']);
    $this->assertSame(['read', 'write'], $result['details']['current_scopes']);
    $this->assertStringContainsString('Required: \'admin\'', $result['error']);
    $this->assertStringContainsString('Current scopes: read, write', $result['error']);
  }

}

