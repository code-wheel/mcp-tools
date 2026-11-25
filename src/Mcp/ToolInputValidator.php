<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\tool\Tool\ToolDefinition;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Validates tool arguments against Tool API-derived JSON schema.
 */
class ToolInputValidator {

  /**
   * The validator.
   *
   * @var \Opis\JsonSchema\Validator
   */
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

    // Cast to object before normalizing: an empty PHP array encodes to "[]"
    // (JSON array), which fails validation against {"type": "object"}. Tools
    // with only optional inputs are legitimately called with no arguments.
    $data = $this->emptyObjects($this->normalizeForValidation((object) $arguments), $schemaObject);

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
      'errors' => $this->describeAllowedParameters($this->collectErrors($error), $definition),
    ];
  }

  /**
   * Appends the allowed parameter list to unknown-parameter errors.
   *
   * @param array<int, array<string, mixed>> $errors
   *   Collected validation errors.
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool definition the arguments were validated against.
   *
   * @return array<int, array<string, mixed>>
   *   The errors, with additionalProperties messages made actionable.
   */
  private function describeAllowedParameters(array $errors, ToolDefinition $definition): array {
    $allowed = array_keys($definition->getInputDefinitions());
    // A tool with no inputs says so: "not allowed: limit" alone left a
    // model that asked for less without a way on.
    $wayOn = $allowed === []
      ? '. This tool takes no parameters: call it with {}.'
      : '. Unknown parameters are rejected; allowed parameters: ' . implode(', ', $allowed) . '.';

    foreach ($errors as &$error) {
      if (($error['keyword'] ?? '') === 'additionalProperties') {
        $error['message'] = rtrim((string) $error['message'], '.') . $wayOn;
      }
    }

    return $errors;
  }

  /**
   * An empty object given where an object is wanted, as an object again.
   *
   * JSON {} is an empty PHP array, and it comes back as [] here. Inside the
   * arguments too: "updates": {} was refused with "The data (array) must
   * match the type: object", and a model then sent [{}], taking an empty
   * change for a fault in its shape. The tool now gets it and says what is
   * wrong.
   */
  private function emptyObjects(mixed $data, mixed $schema): mixed {
    if (!is_object($schema)) {
      return $data;
    }
    $types = (array) ($schema->type ?? []);
    if ($data === [] && in_array('object', $types, TRUE) && !in_array('array', $types, TRUE)) {
      return new \stdClass();
    }
    if (is_object($data) && isset($schema->properties) && is_object($schema->properties)) {
      foreach (get_object_vars($schema->properties) as $name => $property) {
        if (property_exists($data, $name)) {
          $data->{$name} = $this->emptyObjects($data->{$name}, $property);
        }
      }
    }
    return $data;
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
   * Collect Errors.
   *
   * @return array<int,
   *   array<string, mixed>>
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
   * Formats error.
   *
   * @return array<string,
   *   mixed>
   */
  private function formatError(ValidationError $error): array {
    $path = $error->data()->fullPath();
    $pathString = '';
    if (!empty($path)) {
      $pathString = implode('.', array_map(static fn($segment) => (string) $segment, $path));
    }

    return [
      // message() is a template such as "The required properties
      // ({missing}) are missing". The formatter fills in the values.
      'message' => (new ErrorFormatter())->formatErrorMessage($error),
      'keyword' => $error->keyword(),
      'path' => $pathString !== '' ? $pathString : NULL,
    ];
  }

}
