<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\tool\Tool\ToolDefinition;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Validates tool arguments against Tool API-derived JSON schema.
 */
class ToolInputValidator {

  private Validator $validator;

  public function __construct(
    private readonly ToolApiSchemaConverter $schemaConverter,
    private readonly LoggerInterface $logger = new NullLogger(),
    ?Validator $validator = NULL,
  ) {
    $this->validator = $validator ?? new Validator();
    $this->validator->setStopAtFirstError(FALSE);
    $this->validator->setMaxErrors(10);
  }

  /**
   * Validate tool arguments.
   *
   * @return array{valid: bool, errors: array<int, array<string, mixed>>}
   *   Validation result with optional error details.
   */
  public function validate(ToolDefinition $definition, array $arguments): array {
    $schema = $this->schemaConverter->toolDefinitionToInputSchema($definition);

    $schemaObject = $this->normalizeForValidation($schema);
    if (!is_object($schemaObject)) {
      $this->logger->warning('Failed to normalize tool schema for validation.');
      return ['valid' => TRUE, 'errors' => []];
    }

    $data = $this->normalizeForValidation($arguments);

    $result = $this->validator->validate($data, $schemaObject);
    if ($result->isValid()) {
      return ['valid' => TRUE, 'errors' => []];
    }

    $error = $result->error();
    if (!$error instanceof ValidationError) {
      return ['valid' => FALSE, 'errors' => [['message' => 'Invalid input.']]];
    }

    return [
      'valid' => FALSE,
      'errors' => $this->collectErrors($error),
    ];
  }

  /**
   * Normalize data for Opis JSON schema validation.
   */
  private function normalizeForValidation(mixed $value): mixed {
    $encoded = json_encode($value);
    if ($encoded === FALSE) {
      return $value;
    }

    return json_decode($encoded);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function collectErrors(ValidationError $error): array {
    $errors = [$this->formatError($error)];

    foreach ($error->subErrors() as $subError) {
      if ($subError instanceof ValidationError) {
        $errors = array_merge($errors, $this->collectErrors($subError));
      }
    }

    return $errors;
  }

  /**
   * @return array<string, mixed>
   */
  private function formatError(ValidationError $error): array {
    $path = $error->data()->fullPath();
    $pathString = '';
    if (!empty($path)) {
      $pathString = implode('.', array_map(static fn($segment) => (string) $segment, $path));
    }

    return [
      'message' => $error->message(),
      'keyword' => $error->keyword(),
      'path' => $pathString !== '' ? $pathString : NULL,
    ];
  }

}
