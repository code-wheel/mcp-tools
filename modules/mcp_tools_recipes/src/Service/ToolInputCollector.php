<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Service;

use Drupal\Core\Recipe\InputCollectorInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Recipe inputs from a tool call: what was given, else the default.
 *
 * The apply tool never collected inputs, so Drupal had none, and every
 * ${name} placeholder of a recipe was stored as it was written. Drush asks
 * on the console (ConsoleInputCollector); a tool call gives them as a map.
 */
final class ToolInputCollector implements InputCollectorInterface {

  /**
   * The names that were asked for, to tell given names nobody wanted.
   *
   * @var string[]
   */
  public array $asked = [];

  /**
   * The values the call gave.
   *
   * @param array $values
   *   Input values by "recipe.input" or by the input name alone.
   */
  public function __construct(private readonly array $values) {}

  /**
   * {@inheritdoc}
   */
  public function collectValue(string $name, DataDefinitionInterface $definition, mixed $default_value): mixed {
    $this->asked[] = $name;
    $short = substr($name, strrpos($name, '.') + 1);
    return $this->values[$name] ?? $this->values[$short] ?? $default_value;
  }

  /**
   * The given names that no input of the recipe has.
   *
   * @return string[]
   *   Those names.
   */
  public function unused(): array {
    $known = $this->asked;
    foreach ($this->asked as $name) {
      $known[] = substr($name, strrpos($name, '.') + 1);
    }
    return array_values(array_diff(array_keys($this->values), $known));
  }

}
