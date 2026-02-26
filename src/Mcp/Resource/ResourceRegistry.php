<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Resource;

/**
 * Collects MCP resource definitions from tagged providers.
 */
class ResourceRegistry {

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\mcp_tools\Mcp\Resource\ResourceProviderInterface[]
   */
  private array $providers = [];

  /**
   * Cached component lists.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  private array $cache = [];

  /**
   * Constructs a new instance.
   *
   * @param iterable<\Drupal\mcp_tools\Mcp\Resource\ResourceProviderInterface> $providers
   *   Tagged resource providers.
   */
  public function __construct(iterable $providers = []) {
    foreach ($providers as $provider) {
      $this->providers[] = $provider;
    }
  }

  /**
   * Returns all resource definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Resource definitions.
   */
  public function getResources(): array {
    if (isset($this->cache['resources'])) {
      return $this->cache['resources'];
    }

    $resources = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->getResources() as $resource) {
        $resources[] = $resource;
      }
    }

    $this->cache['resources'] = $resources;
    return $resources;
  }

  /**
   * Returns all resource template definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Resource template definitions.
   */
  public function getResourceTemplates(): array {
    if (isset($this->cache['templates'])) {
      return $this->cache['templates'];
    }

    $templates = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->getResourceTemplates() as $template) {
        $templates[] = $template;
      }
    }

    $this->cache['templates'] = $templates;
    return $templates;
  }

}
