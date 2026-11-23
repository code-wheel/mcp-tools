<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * The status report lines, for Drupal 11.3 and later.
 *
 * The checks are in mcp_tools_remote_requirements(), which Drupal before 11.3
 * calls itself.
 */
final class McpToolsRemoteRequirementsHooks {

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    require_once dirname(__DIR__, 2) . '/mcp_tools_remote.install';
    return mcp_tools_remote_requirements('runtime');
  }

}
