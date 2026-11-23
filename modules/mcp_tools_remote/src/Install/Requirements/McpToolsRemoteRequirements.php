<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;

/**
 * The install-time checks, for Drupal 11.3 and later.
 *
 * The checks are in mcp_tools_remote_requirements(), which Drupal before 11.3
 * calls itself. Drupal 11.3 and later run this class at install time.
 */
final class McpToolsRemoteRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    require_once dirname(__DIR__, 3) . '/mcp_tools_remote.install';
    return mcp_tools_remote_requirements('install');
  }

}
