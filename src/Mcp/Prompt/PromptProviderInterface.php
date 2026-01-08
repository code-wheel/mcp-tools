<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Prompt;

/**
 * Provides MCP prompt definitions for MCP Tools.
 */
interface PromptProviderInterface {

  /**
   * Returns MCP prompt definitions.
   *
   * @return array<int, array{
   *   handler: callable,
   *   name?: string,
   *   description?: string,
   *   icons?: array<int, mixed>|null,
   *   meta?: array<string, mixed>|null,
   * }>
   *   Prompt definitions.
   */
  public function getPrompts(): array;

}
