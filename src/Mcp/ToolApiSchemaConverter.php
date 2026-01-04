<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;

/**
 * Converts Tool API definitions into MCP-compatible JSON Schemas and hints.
 */
final class ToolApiSchemaConverter {

  /**
   * Builds an MCP inputSchema JSON Schema from a Tool API ToolDefinition.
   *
   * @return array<string, mixed>
   *   MCP-compatible JSON Schema.
   */
  public function toolDefinitionToInputSchema(ToolDefinition $definition): array {
    return $this->inputDefinitionsToSchema($definition->getInputDefinitions());
  }

  /**
   * Derives MCP ToolAnnotations hint values from a ToolDefinition.
   *
   * @return array<string, mixed>
   *   Keys correspond to Mcp\Schema\ToolAnnotations fields.
   */
  public function toolDefinitionToAnnotations(ToolDefinition $definition): array {
    $operation = $definition->getOperation() ?? ToolOperation::Transform;
    $readOnly = $operation === ToolOperation::Read;

    // Idempotent hint: Read operations are always idempotent (no state change).
    // Write/Trigger operations are conservatively marked as not idempotent
    // since creates/triggers may have different effects on repeated calls.
    // This could be refined per-tool in the future.
    $idempotent = $readOnly ? TRUE : NULL;

    return [
      'title' => (string) $definition->getLabel(),
      'readOnlyHint' => $readOnly,
      'destructiveHint' => $definition->isDestructive() ?: NULL,
      'idempotentHint' => $idempotent,
      // Drupal site operations are a closed world (not web search, etc).
      'openWorldHint' => FALSE,
    ];
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

    // Map Drupal typed data types to JSON Schema types.
    $typeMap = [
      'string' => 'string',
      'integer' => 'integer',
      'float' => 'number',
      'boolean' => 'boolean',
      'email' => 'string',
      'uri' => 'string',
      'datetime_iso8601' => 'string',
      'timestamp' => 'integer',
      'list' => 'array',
      'map' => 'object',
    ];

    $schema = [];

    // Handle entity types as strings: callers should pass IDs/handles.
    if (str_starts_with($dataType, 'entity:') ||
      str_starts_with($dataType, 'entity_reference:') ||
      $dataType === 'entity') {
      $schema['type'] = 'string';
      $schema['description'] = 'Entity objects should be passed using an ID or a handle token returned by a previous tool call.';
    }
    elseif (isset($typeMap[$dataType])) {
      $schema['type'] = $typeMap[$dataType];
    }
    else {
      // Default to string for unknown types.
      $schema['type'] = 'string';
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
