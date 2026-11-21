<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Traits;

/**
 * What the tests of other modules' tools look at: the audit log and a flag.
 *
 * For kernel tests with the foreign_tool_test module and dblog's watchdog
 * table installed.
 */
trait ForeignToolTestTrait {

  /**
   * The audit lines so far, oldest first.
   *
   * @return string[]
   *   One string per line, with its values filled in.
   */
  protected function auditLines(): array {
    $lines = [];
    $rows = $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['message', 'variables'])
      ->condition('type', 'mcp_tools')
      ->orderBy('wid')
      ->execute();
    foreach ($rows as $row) {
      $lines[] = strtr($row->message, (array) unserialize($row->variables, ['allowed_classes' => FALSE]));
    }
    return $lines;
  }

  /**
   * Whether the test module's write tool wrote since the last look.
   */
  protected function wroteSinceLastLook(): bool {
    $state = $this->container->get('state');
    $wrote = (bool) $state->get('foreign_tool_test.wrote', FALSE);
    $state->delete('foreign_tool_test.wrote');
    return $wrote;
  }

}
