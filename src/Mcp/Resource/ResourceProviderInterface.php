<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Resource;

/**
 * Provides MCP resource definitions for MCP Tools.
 */
interface ResourceProviderInterface {

  /**
   * Returns MCP resource definitions.
   *
   * @return array<int, array{
   *   uri: string,
   *   handler: callable,
   *   name?: string,
   *   description?: string,
   *   mimeType?: string,
   *   size?: int|null,
   *   annotations?: array<string, mixed>|null,
   *   icons?: array<int, mixed>|null,
   *   meta?: array<string, mixed>|null,
   *   }>
   *   Resource definitions.
   */
  public function getResources(): array;

  /**
   * Returns MCP resource template definitions.
   *
   * @return array<int, array{
   *   uriTemplate: string,
   *   handler: callable,
   *   name?: string,
   *   description?: string,
   *   mimeType?: string,
   *   annotations?: array<string, mixed>|null,
   *   meta?: array<string, mixed>|null,
   *   }>
   *   Resource template definitions.
   */
  public function getResourceTemplates(): array;

}
