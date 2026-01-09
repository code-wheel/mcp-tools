<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Tool\ToolDefinition;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ToolInputValidator::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolInputValidatorTest extends UnitTestCase {

  protected ToolApiSchemaConverter $schemaConverter;
  protected LoggerInterface $logger;

  protected function setUp(): void {
    parent::setUp();
    $this->schemaConverter = $this->createMock(ToolApiSchemaConverter::class);
    $this->logger = $this->createMock(LoggerInterface::class);
  }

  protected function createValidator(): ToolInputValidator {
    return new ToolInputValidator($this->schemaConverter, $this->logger);
  }

  protected function createMockDefinition(): ToolDefinition {
    return $this->createMock(ToolDefinition::class);
  }

  public function testValidateReturnsValidForMatchingInput(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'name' => ['type' => 'string'],
          'count' => ['type' => 'integer'],
        ],
        'required' => ['name'],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, [
      'name' => 'test',
      'count' => 5,
    ]);

    $this->assertTrue($result['valid']);
    $this->assertEmpty($result['errors']);
  }

  public function testValidateReturnsValidWhenOptionalFieldMissing(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'name' => ['type' => 'string'],
          'description' => ['type' => 'string'],
        ],
        'required' => ['name'],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['name' => 'test']);

    $this->assertTrue($result['valid']);
    $this->assertEmpty($result['errors']);
  }

  public function testValidateReturnsErrorsForMissingRequiredField(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'name' => ['type' => 'string'],
        ],
        'required' => ['name'],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, []);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
  }

  public function testValidateReturnsErrorsForWrongType(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'count' => ['type' => 'integer'],
        ],
        'required' => [],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['count' => 'not-a-number']);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
  }

  public function testValidateReturnsErrorsForConstraintViolation(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'name' => [
            'type' => 'string',
            'minLength' => 5,
          ],
        ],
        'required' => ['name'],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['name' => 'hi']);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
    $this->assertSame('minLength', $result['errors'][0]['keyword']);
  }

  public function testValidateReturnsErrorsForEnumViolation(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'status' => [
            'type' => 'string',
            'enum' => ['draft', 'published'],
          ],
        ],
        'required' => ['status'],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['status' => 'invalid']);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
  }

  public function testValidateHandlesArrayInput(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'tags' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
          ],
        ],
        'required' => [],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['tags' => ['one', 'two']]);

    $this->assertTrue($result['valid']);
    $this->assertEmpty($result['errors']);
  }

  public function testValidateReturnsErrorsForInvalidArrayItems(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'tags' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
          ],
        ],
        'required' => [],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['tags' => ['one', 123, 'two']]);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
  }

  public function testValidateReturnsValidForEmptyPropertiesSchema(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => new \stdClass(),
        'required' => [],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, []);

    $this->assertTrue($result['valid']);
    $this->assertEmpty($result['errors']);
  }

  public function testValidateHandlesNestedObjects(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'config' => [
            'type' => 'object',
            'properties' => [
              'enabled' => ['type' => 'boolean'],
              'limit' => ['type' => 'integer'],
            ],
            'required' => ['enabled'],
          ],
        ],
        'required' => [],
      ]);

    $validator = $this->createValidator();

    $validResult = $validator->validate($definition, [
      'config' => ['enabled' => TRUE, 'limit' => 10],
    ]);
    $this->assertTrue($validResult['valid']);

    $invalidResult = $validator->validate($definition, [
      'config' => ['limit' => 10],
    ]);
    $this->assertFalse($invalidResult['valid']);
  }

  public function testValidateReturnsValidWhenSchemaCannotBeNormalized(): void {
    $definition = $this->createMockDefinition();

    // Return a schema that will fail JSON encoding (recursive reference).
    $badSchema = [];
    $badSchema['recursive'] = &$badSchema;

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn($badSchema);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with($this->stringContains('Failed to normalize'));

    $validator = $this->createValidator();
    $result = $validator->validate($definition, ['anything' => 'goes']);

    // When normalization fails, validation passes (fail-open for usability).
    $this->assertTrue($result['valid']);
  }

  public function testErrorsContainPathForNestedViolations(): void {
    $definition = $this->createMockDefinition();

    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn([
        'type' => 'object',
        'properties' => [
          'user' => [
            'type' => 'object',
            'properties' => [
              'age' => ['type' => 'integer'],
            ],
          ],
        ],
        'required' => [],
      ]);

    $validator = $this->createValidator();
    $result = $validator->validate($definition, [
      'user' => ['age' => 'not-a-number'],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);

    // Check that at least one error has a path.
    $hasPath = FALSE;
    foreach ($result['errors'] as $error) {
      if (!empty($error['path'])) {
        $hasPath = TRUE;
        break;
      }
    }
    $this->assertTrue($hasPath, 'Expected at least one error with a path');
  }

}
