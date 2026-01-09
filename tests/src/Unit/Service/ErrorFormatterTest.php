<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\mcp_tools\Service\ErrorFormatter;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\ErrorFormatter::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ErrorFormatterTest extends UnitTestCase {

  private ErrorFormatter $formatter;

  protected function setUp(): void {
    parent::setUp();
    $this->formatter = new ErrorFormatter();
  }

  public function testNotFoundIncludesSuggestionWhenProvided(): void {
    $result = $this->formatter->notFound('user', '99', 'Try listing users first.');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_NOT_FOUND, $result['code']);
    $this->assertStringContainsString("The user '99' was not found.", $result['error']);
    $this->assertStringContainsString('Try listing users first.', $result['error']);
    $this->assertSame('user', $result['details']['entity_type']);
    $this->assertSame('99', $result['details']['identifier']);
  }

  public function testValidationDoesNotEchoSensitiveValues(): void {
    $result = $this->formatter->validation('password', 'too short', 'secret');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_VALIDATION, $result['code']);
    $this->assertArrayHasKey('details', $result);
    $this->assertSame('password', $result['details']['field']);
    $this->assertArrayNotHasKey('value', $result['details']);
  }

  public function testValidationIncludesValueForNonSensitiveFields(): void {
    $result = $this->formatter->validation('title', 'required', '');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_VALIDATION, $result['code']);
    $this->assertSame('', $result['details']['value']);
  }

  public function testSuccessMergesData(): void {
    $result = $this->formatter->success('OK', ['data' => ['x' => 1]]);

    $this->assertTrue($result['success']);
    $this->assertSame('OK', $result['message']);
    $this->assertSame(['x' => 1], $result['data']);
  }

  public function testInsufficientScopeIncludesScopesInDetails(): void {
    $result = $this->formatter->insufficientScope('admin', ['read', 'write']);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_SCOPE, $result['code']);
    $this->assertSame('admin', $result['details']['required_scope']);
    $this->assertSame(['read', 'write'], $result['details']['current_scopes']);
    $this->assertStringContainsString('Required: \'admin\'', $result['error']);
    $this->assertStringContainsString('Current scopes: read, write', $result['error']);
  }

  public function testAlreadyExistsReturnsCorrectFormat(): void {
    $result = $this->formatter->alreadyExists('content type', 'article');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_ALREADY_EXISTS, $result['code']);
    $this->assertStringContainsString("content type with ID 'article' already exists", $result['error']);
    $this->assertSame('content type', $result['details']['entity_type']);
    $this->assertSame('article', $result['details']['identifier']);
  }

  public function testPermissionDeniedWithoutPermission(): void {
    $result = $this->formatter->permissionDenied('delete node');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_PERMISSION, $result['code']);
    $this->assertStringContainsString('Permission denied for operation: delete node', $result['error']);
    $this->assertNull($result['details']['required_permission']);
  }

  public function testPermissionDeniedWithPermission(): void {
    $result = $this->formatter->permissionDenied('administer site', 'administer site configuration');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_PERMISSION, $result['code']);
    $this->assertStringContainsString('Required permission: administer site configuration', $result['error']);
    $this->assertSame('administer site configuration', $result['details']['required_permission']);
  }

  public function testProtectedEntityReturnsCorrectFormat(): void {
    $result = $this->formatter->protectedEntity('role', 'administrator', 'System role cannot be deleted.');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_PROTECTED, $result['code']);
    $this->assertStringContainsString("role 'administrator' is protected", $result['error']);
    $this->assertStringContainsString('System role cannot be deleted.', $result['error']);
    $this->assertSame('role', $result['details']['entity_type']);
    $this->assertSame('administrator', $result['details']['identifier']);
    $this->assertSame('System role cannot be deleted.', $result['details']['reason']);
  }

  public function testEntityInUseWithForceAvailable(): void {
    $result = $this->formatter->entityInUse('vocabulary', 'tags', 42, TRUE);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_IN_USE, $result['code']);
    $this->assertStringContainsString("vocabulary 'tags': it is used in 42 places", $result['error']);
    $this->assertStringContainsString('force=true', $result['error']);
    $this->assertSame(42, $result['details']['usage_count']);
    $this->assertTrue($result['details']['force_available']);
  }

  public function testEntityInUseWithoutForce(): void {
    $result = $this->formatter->entityInUse('field', 'field_body', 10, FALSE);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_IN_USE, $result['code']);
    $this->assertStringNotContainsString('force=true', $result['error']);
    $this->assertFalse($result['details']['force_available']);
  }

  public function testMissingDependencyReturnsCorrectFormat(): void {
    $result = $this->formatter->missingDependency('media', 'media operations');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_DEPENDENCY, $result['code']);
    $this->assertStringContainsString("'media' is required for media operations", $result['error']);
    $this->assertSame('media', $result['details']['dependency']);
    $this->assertSame('media operations', $result['details']['required_for']);
  }

  public function testRateLimitExceededReturnsCorrectFormat(): void {
    $result = $this->formatter->rateLimitExceeded('write', 60);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_RATE_LIMIT, $result['code']);
    $this->assertStringContainsString('Rate limit exceeded for write operations', $result['error']);
    $this->assertStringContainsString('60 seconds', $result['error']);
    $this->assertSame('write', $result['details']['limit_type']);
    $this->assertSame(60, $result['details']['retry_after']);
  }

  public function testReadOnlyModeReturnsCorrectFormat(): void {
    $result = $this->formatter->readOnlyMode();

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_READ_ONLY, $result['code']);
    $this->assertStringContainsString('Write operations are disabled', $result['error']);
    $this->assertStringContainsString('read-only mode', $result['error']);
  }

  public function testErrorReturnsBasicFormat(): void {
    $result = $this->formatter->error('Something went wrong');

    $this->assertFalse($result['success']);
    $this->assertSame('Something went wrong', $result['error']);
    $this->assertSame(ErrorFormatter::ERROR_INTERNAL, $result['code']);
    $this->assertArrayNotHasKey('details', $result);
  }

  public function testErrorWithDetails(): void {
    $result = $this->formatter->error('Custom error', 'CUSTOM_CODE', ['foo' => 'bar']);

    $this->assertFalse($result['success']);
    $this->assertSame('Custom error', $result['error']);
    $this->assertSame('CUSTOM_CODE', $result['code']);
    $this->assertSame(['foo' => 'bar'], $result['details']);
  }

  public function testFromExceptionWrapsException(): void {
    $exception = new \RuntimeException('Database connection failed');
    $result = $this->formatter->fromException($exception);

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_INTERNAL, $result['code']);
    $this->assertStringContainsString('Database connection failed', $result['error']);
    $this->assertSame('RuntimeException', $result['details']['exception']);
  }

  public function testFromExceptionWithContext(): void {
    $exception = new \InvalidArgumentException('Invalid ID');
    $result = $this->formatter->fromException($exception, 'Loading node');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Loading node: Invalid ID', $result['error']);
    $this->assertSame('InvalidArgumentException', $result['details']['exception']);
  }

  public function testNotFoundWithoutSuggestion(): void {
    $result = $this->formatter->notFound('node', '123');

    $this->assertFalse($result['success']);
    $this->assertSame(ErrorFormatter::ERROR_NOT_FOUND, $result['code']);
    $this->assertSame("The node '123' was not found.", $result['error']);
  }

  public function testValidationWithNullValue(): void {
    $result = $this->formatter->validation('email', 'invalid format', NULL);

    $this->assertFalse($result['success']);
    $this->assertArrayNotHasKey('value', $result['details']);
  }

  public function testValidationSensitiveFieldPatterns(): void {
    $sensitiveFields = ['password', 'user_pass', 'secret_key', 'api_token', 'auth_key', 'credential_data'];

    foreach ($sensitiveFields as $field) {
      $result = $this->formatter->validation($field, 'invalid', 'sensitive_value');
      $this->assertArrayNotHasKey('value', $result['details'], "Field '$field' should be sensitive");
    }
  }

}

