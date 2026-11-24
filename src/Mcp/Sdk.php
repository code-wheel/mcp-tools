<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

/**
 * Finds mcp/sdk classes that changed their name between SDK versions.
 */
final class Sdk {

  /**
   * What to run when mcp/sdk is missing.
   *
   * Names the newest SDK line composer.json allows. A unit test keeps the
   * two in step.
   */
  public const INSTALL_COMMAND = 'composer require mcp/sdk:^0.10';

  /**
   * Returns the SDK's server class, or NULL when mcp/sdk is not installed.
   *
   * It is Mcp\Server up to mcp/sdk 0.9 and Mcp\Server\Server from 0.10,
   * which split the SDK into the Mcp\Schema, Mcp\Client and Mcp\Server
   * namespaces.
   *
   * @return class-string|null
   *   The class name, or NULL.
   */
  public static function serverClass(): ?string {
    foreach (['Mcp\Server\Server', 'Mcp\Server'] as $class) {
      if (class_exists($class)) {
        return $class;
      }
    }
    return NULL;
  }

  /**
   * The registry class: Mcp\Capability\Registry before 0.10, then in Server.
   */
  public static function registryClass(): ?string {
    foreach (['Mcp\Server\Capability\Registry', 'Mcp\Capability\Registry'] as $class) {
      if (class_exists($class)) {
        return $class;
      }
    }
    return NULL;
  }

}
