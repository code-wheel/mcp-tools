<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use CodeWheel\McpSchemaBuilder\TypeMapper;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;

/**
 * Converts Tool API definitions into MCP-compatible JSON Schemas and hints.
 */
class ToolApiSchemaConverter {

  /**
   * In-memory caches keyed by caller-provided IDs.
   *
   * @var array<string, array<string, mixed>>
   */
  private array $inputSchemaCache = [];

  /**
   * @var array<string, array<string, mixed>>
   */
  private array $annotationCache = [];

  /**
   * Type mapper for converting data types to JSON Schema types.
   */
  private TypeMapper $typeMapper;

  /**
   * Constructs a new ToolApiSchemaConverter.
   */
  public function __construct() {
    $this->typeMapper = new TypeMapper();
  }

  /**
   * Builds an MCP inputSchema JSON Schema from a Tool API ToolDefinition.
   *
   * @param string|null $cacheKey
   *   Optional cache key (for example, a plugin ID).
   *
   * @return array<string, mixed>
   *   MCP-compatible JSON Schema.
   */
  public function toolDefinitionToInputSchema(ToolDefinition $definition, ?string $cacheKey = NULL): array {
    if ($cacheKey !== NULL && isset($this->inputSchemaCache[$cacheKey])) {
      return $this->inputSchemaCache[$cacheKey];
    }

    $schema = $this->inputDefinitionsToSchema($definition->getInputDefinitions());

    if ($cacheKey !== NULL) {
      $this->inputSchemaCache[$cacheKey] = $schema;
    }

    return $schema;
  }

  /**
   * Derives MCP ToolAnnotations hint values from a ToolDefinition.
   *
   * @return array<string, mixed>
   *   Keys correspond to Mcp\Schema\ToolAnnotations fields.
   */
  public function toolDefinitionToAnnotations(ToolDefinition $definition, ?string $cacheKey = NULL): array {
    if ($cacheKey !== NULL && isset($this->annotationCache[$cacheKey])) {
      return $this->annotationCache[$cacheKey];
    }

    $operation = $definition->getOperation() ?? ToolOperation::Transform;
    $readOnly = $operation === ToolOperation::Read;

    // Idempotent hint: Read operations are always idempotent (no state change).
    // Write/Trigger operations are conservatively marked as not idempotent
    // since creates/triggers may have different effects on repeated calls.
    // This could be refined per-tool in the future.
    $idempotent = $readOnly ? TRUE : NULL;

    $annotations = [
      'title' => (string) $definition->getLabel(),
      'readOnlyHint' => $readOnly,
      'destructiveHint' => $definition->isDestructive() ?: NULL,
      'idempotentHint' => $idempotent,
      // Drupal site operations are a closed world (not web search, etc).
      'openWorldHint' => FALSE,
    ];

    if ($cacheKey !== NULL) {
      $this->annotationCache[$cacheKey] = $annotations;
    }

    return $annotations;
  }

  /**
   * Converts Tool API input definitions to an MCP JSON schema object.
   *
   * @param array<string, \Drupal\tool\TypedData\InputDefinitionInterface> $input_definitions
   *   Input definitions keyed by name.
   *
   * @return array<string, mixed>
   *   JSON schema.
   */
  private function inputDefinitionsToSchema(array $input_definitions): array {
    $properties = [];
    $required = [];

    foreach ($input_definitions as $name => $input_definition) {
      $properties[$name] = $this->inputDefinitionToSchema($input_definition);
      if ($input_definition->isRequired()) {
        $required[] = $name;
      }
    }

    $schema = [
      'type' => 'object',
      // MCP clients expect JSON Schema "properties" to be an object. In PHP,
      // an empty array would JSON-encode to `[]`, so use an empty object.
      'properties' => !empty($properties) ? $properties : new \stdClass(),
    ];

    if (!empty($required)) {
      $schema['required'] = $required;
    }

    return $schema;
  }

  /**
   * Converts an InputDefinition into a JSON schema property.
   *
   * @return array<string, mixed>
   *   JSON schema for the property.
   */
  private function inputDefinitionToSchema(InputDefinitionInterface $definition): array {
    $dataType = $definition->getDataType();

    // Use TypeMapper to convert the data type to JSON Schema type.
    $jsonType = $this->typeMapper->mapType($dataType);
    $format = $this->typeMapper->getFormat($dataType);

    $schema = ['type' => $jsonType];

    // Add format hint if available (e.g., email, uri, date-time).
    if ($format !== NULL) {
      $schema['format'] = $format;
    }

    // Handle entity types with a helpful description.
    if (str_starts_with($dataType, 'entity:') ||
      str_starts_with($dataType, 'entity_reference:') ||
      $dataType === 'entity') {
      $schema['description'] = 'Entity objects should be passed using an ID or a handle token returned by a previous tool call.';
    }

    // Add description if available.
    $description = $definition->getDescription();
    if ($description) {
      $existing = $schema['description'] ?? '';
      $schema['description'] = $existing ? $existing . ' ' . (string) $description : (string) $description;
    }

    // Provide best-effort list and map schemas when possible.
    if ($definition instanceof ListInputDefinition || $dataType === 'list' || $definition->isMultiple()) {
      $schema['type'] = 'array';
      $schema['items'] = ['type' => 'string'];
      if ($definition instanceof ListInputDefinition) {
        $itemDefinition = $definition->getDataDefinition()->getItemDefinition();
        if ($itemDefinition instanceof InputDefinitionInterface) {
          $schema['items'] = $this->inputDefinitionToSchema($itemDefinition);
        }
      }
    }
    elseif ($definition instanceof MapInputDefinition || $dataType === 'map') {
      $schema['type'] = 'object';
    }

    // Add constraints as additional schema properties.
    $constraints = $definition->getConstraints();
    foreach ($constraints as $constraintName => $constraintConfig) {
      match ($constraintName) {
        'Length' => $this->addLengthConstraints($schema, $constraintConfig),
        'Range' => $this->addRangeConstraints($schema, $constraintConfig),
        'Regex' => $this->addRegexConstraint($schema, $constraintConfig),
        'AllowedValues' => $this->addEnumConstraint($schema, $constraintConfig),
        default => NULL,
      };
    }

    return $schema;
  }

  /**
   * Adds length constraints to schema.
   *
   * @param array<string, mixed> $schema
   *   The schema array to modify.
   * @param array|object $constraint
   *   The constraint configuration.
   */
  private function addLengthConstraints(array &$schema, array|object $constraint): void {
    $min = is_array($constraint) ? ($constraint['min'] ?? NULL) : ($constraint->min ?? NULL);
    $max = is_array($constraint) ? ($constraint['max'] ?? NULL) : ($constraint->max ?? NULL);

    if ($min !== NULL) {
      $schema['minLength'] = $min;
    }
    if ($max !== NULL) {
      $schema['maxLength'] = $max;
    }
  }

  /**
   * Adds range constraints to schema.
   *
   * @param array<string, mixed> $schema
   *   The schema array to modify.
   * @param array|object $constraint
   *   The constraint configuration.
   */
  private function addRangeConstraints(array &$schema, array|object $constraint): void {
    $min = is_array($constraint) ? ($constraint['min'] ?? NULL) : ($constraint->min ?? NULL);
    $max = is_array($constraint) ? ($constraint['max'] ?? NULL) : ($constraint->max ?? NULL);

    if ($min !== NULL) {
      $schema['minimum'] = $min;
    }
    if ($max !== NULL) {
      $schema['maximum'] = $max;
    }
  }

  /**
   * Adds regex pattern constraint to schema.
   *
   * @param array<string, mixed> $schema
   *   The schema array to modify.
   * @param array|object $constraint
   *   The constraint configuration.
   */
  private function addRegexConstraint(array &$schema, array|object $constraint): void {
    $pattern = is_array($constraint) ? ($constraint['pattern'] ?? NULL) : ($constraint->pattern ?? NULL);
    if ($pattern !== NULL) {
      $schema['pattern'] = $pattern;
    }
  }

  /**
   * Adds enum constraint to schema.
   *
   * @param array<string, mixed> $schema
   *   The schema array to modify.
   * @param array|object $constraint
   *   The constraint configuration.
   */
  private function addEnumConstraint(array &$schema, array|object $constraint): void {
    $choices = is_array($constraint) ? ($constraint['choices'] ?? NULL) : ($constraint->choices ?? NULL);
    if (is_array($choices) && !empty($choices)) {
      $schema['enum'] = array_values($choices);
    }
  }

}
