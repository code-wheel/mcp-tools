<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(ErrorCode::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ErrorCodeTest extends UnitTestCase {

  public function testAllReturnsDefinedConstants(): void {
    $all = ErrorCode::all();

    $this->assertIsArray($all);
    $this->assertNotEmpty($all);
    $this->assertArrayHasKey('NOT_FOUND', $all);
    $this->assertArrayHasKey('VALIDATION_ERROR', $all);
    $this->assertArrayHasKey('INSUFFICIENT_SCOPE', $all);
  }

  public function testIsValidReturnsTrueForDefinedCodes(): void {
    $this->assertTrue(ErrorCode::isValid(ErrorCode::NOT_FOUND));
    $this->assertTrue(ErrorCode::isValid(ErrorCode::VALIDATION_ERROR));
    $this->assertTrue(ErrorCode::isValid(ErrorCode::INTERNAL_ERROR));
  }

  public function testIsValidReturnsFalseForUnknownCodes(): void {
    $this->assertFalse(ErrorCode::isValid('UNKNOWN_CODE'));
    $this->assertFalse(ErrorCode::isValid(''));
    $this->assertFalse(ErrorCode::isValid('not_found'));
  }

  public function testGetCategoryReturnsAccessForAccessCodes(): void {
    $this->assertSame('access', ErrorCode::getCategory(ErrorCode::INSUFFICIENT_SCOPE));
    $this->assertSame('access', ErrorCode::getCategory(ErrorCode::ADMIN_REQUIRED));
    $this->assertSame('access', ErrorCode::getCategory(ErrorCode::ACCESS_DENIED));
    $this->assertSame('access', ErrorCode::getCategory(ErrorCode::RATE_LIMIT_EXCEEDED));
  }

  public function testGetCategoryReturnsResourceForResourceCodes(): void {
    $this->assertSame('resource', ErrorCode::getCategory(ErrorCode::NOT_FOUND));
    $this->assertSame('resource', ErrorCode::getCategory(ErrorCode::ALREADY_EXISTS));
    $this->assertSame('resource', ErrorCode::getCategory(ErrorCode::ENTITY_IN_USE));
    $this->assertSame('resource', ErrorCode::getCategory(ErrorCode::ENTITY_PROTECTED));
  }

  public function testGetCategoryReturnsValidationForValidationCodes(): void {
    $this->assertSame('validation', ErrorCode::getCategory(ErrorCode::VALIDATION_ERROR));
    $this->assertSame('validation', ErrorCode::getCategory(ErrorCode::INVALID_NAME));
    $this->assertSame('validation', ErrorCode::getCategory(ErrorCode::INVALID_FILE_TYPE));
    $this->assertSame('validation', ErrorCode::getCategory(ErrorCode::PAYLOAD_TOO_LARGE));
  }

  public function testGetCategoryReturnsOperationForOperationCodes(): void {
    $this->assertSame('operation', ErrorCode::getCategory(ErrorCode::INTERNAL_ERROR));
    $this->assertSame('operation', ErrorCode::getCategory(ErrorCode::OPERATION_FAILED));
    $this->assertSame('operation', ErrorCode::getCategory(ErrorCode::TIMEOUT));
    $this->assertSame('operation', ErrorCode::getCategory(ErrorCode::CONFIRMATION_REQUIRED));
  }

  public function testGetCategoryReturnsDomainForDomainCodes(): void {
    $this->assertSame('domain', ErrorCode::getCategory(ErrorCode::CRON_FAILED));
    $this->assertSame('domain', ErrorCode::getCategory(ErrorCode::TEMPLATE_NOT_FOUND));
    $this->assertSame('domain', ErrorCode::getCategory(ErrorCode::MIGRATION_FAILED));
    $this->assertSame('domain', ErrorCode::getCategory('UNKNOWN_CODE'));
  }

  public function testIsRecoverableReturnsTrueForRetryableCodes(): void {
    $this->assertTrue(ErrorCode::isRecoverable(ErrorCode::RATE_LIMIT_EXCEEDED));
    $this->assertTrue(ErrorCode::isRecoverable(ErrorCode::TIMEOUT));
    $this->assertTrue(ErrorCode::isRecoverable(ErrorCode::SERVICE_UNAVAILABLE));
    $this->assertTrue(ErrorCode::isRecoverable(ErrorCode::INTERNAL_ERROR));
  }

  public function testIsRecoverableReturnsFalseForPermanentCodes(): void {
    $this->assertFalse(ErrorCode::isRecoverable(ErrorCode::NOT_FOUND));
    $this->assertFalse(ErrorCode::isRecoverable(ErrorCode::VALIDATION_ERROR));
    $this->assertFalse(ErrorCode::isRecoverable(ErrorCode::ACCESS_DENIED));
    $this->assertFalse(ErrorCode::isRecoverable(ErrorCode::ALREADY_EXISTS));
  }

  public function testGetHttpStatusReturns403ForAccessCodes(): void {
    $this->assertSame(403, ErrorCode::getHttpStatus(ErrorCode::INSUFFICIENT_SCOPE));
    $this->assertSame(403, ErrorCode::getHttpStatus(ErrorCode::ADMIN_REQUIRED));
    $this->assertSame(403, ErrorCode::getHttpStatus(ErrorCode::ACCESS_DENIED));
  }

  public function testGetHttpStatusReturns404ForNotFound(): void {
    $this->assertSame(404, ErrorCode::getHttpStatus(ErrorCode::NOT_FOUND));
    $this->assertSame(404, ErrorCode::getHttpStatus(ErrorCode::TEMPLATE_NOT_FOUND));
  }

  public function testGetHttpStatusReturns409ForConflicts(): void {
    $this->assertSame(409, ErrorCode::getHttpStatus(ErrorCode::ALREADY_EXISTS));
    $this->assertSame(409, ErrorCode::getHttpStatus(ErrorCode::ENTITY_IN_USE));
    $this->assertSame(409, ErrorCode::getHttpStatus(ErrorCode::ENTITY_PROTECTED));
  }

  public function testGetHttpStatusReturns400ForValidationErrors(): void {
    $this->assertSame(400, ErrorCode::getHttpStatus(ErrorCode::VALIDATION_ERROR));
    $this->assertSame(400, ErrorCode::getHttpStatus(ErrorCode::INVALID_NAME));
    $this->assertSame(400, ErrorCode::getHttpStatus(ErrorCode::MISSING_REQUIRED));
  }

  public function testGetHttpStatusReturnsSpecialCodes(): void {
    $this->assertSame(429, ErrorCode::getHttpStatus(ErrorCode::RATE_LIMIT_EXCEEDED));
    $this->assertSame(413, ErrorCode::getHttpStatus(ErrorCode::PAYLOAD_TOO_LARGE));
    $this->assertSame(408, ErrorCode::getHttpStatus(ErrorCode::TIMEOUT));
    $this->assertSame(503, ErrorCode::getHttpStatus(ErrorCode::SERVICE_UNAVAILABLE));
  }

  public function testGetHttpStatusReturns500ForUnknown(): void {
    $this->assertSame(500, ErrorCode::getHttpStatus(ErrorCode::INTERNAL_ERROR));
    $this->assertSame(500, ErrorCode::getHttpStatus(ErrorCode::CRON_FAILED));
    $this->assertSame(500, ErrorCode::getHttpStatus('UNKNOWN_CODE'));
  }

  public function testConstantsAreUppercaseWithUnderscores(): void {
    foreach (ErrorCode::all() as $name => $value) {
      $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]+$/', $value, "Error code $name has invalid format: $value");
    }
  }

}
