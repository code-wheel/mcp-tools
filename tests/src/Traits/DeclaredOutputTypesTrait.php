<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Traits;

use Drupal\tool\Tool\ToolInterface;

/**
 * Checks a tool's output values against the types its definitions declare.
 *
 * Tool API accepts "7" for an integer output. A client that checks the result
 * against the advertised outputSchema does not, and mcp/sdk from 0.9 answers
 * such a call with "Invalid structured output" after the tool has already
 * run: the node is updated and the client is told the call failed.
 */
trait DeclaredOutputTypesTrait {

  /**
   * Asserts that every output that is set has its declared type.
   */
  protected function assertOutputsHaveDeclaredTypes(ToolInterface $tool, string $toolId): void {
    $checks = [
      'integer' => 'is_int',
      'string' => 'is_string',
      'boolean' => 'is_bool',
      'float' => static fn(mixed $value): bool => is_int($value) || is_float($value),
      'list' => static fn(mixed $value): bool => is_array($value) && array_is_list($value),
      // A map with entries has to be keyed by name: a PHP list would go
      // out as a JSON array, which the schema of a map does not allow. The
      // server sends an empty map as {}.
      'map' => static fn(mixed $value): bool => is_array($value) && ($value === [] || !array_is_list($value)),
    ];
    $values = $tool->getOutputValues();
    foreach ($tool->getOutputDefinitions() as $name => $definition) {
      $type = (string) $definition->getDataType();
      $value = $values[$name] ?? NULL;
      if ($value === NULL || !isset($checks[$type])) {
        continue;
      }
      $this->assertTrue(
        $checks[$type]($value),
        sprintf('%s: output "%s" is declared %s, got %s.', $toolId, $name, $type, get_debug_type($value)),
      );
    }
  }

}
