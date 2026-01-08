<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Prompt;

/**
 * Collects MCP prompt definitions from tagged providers.
 */
final class PromptRegistry {

  /**
   * @var \Drupal\mcp_tools\Mcp\Prompt\PromptProviderInterface[]
   */
  private array $providers = [];

  /**
   * Cached prompt list.
   *
   * @var array<int, array<string, mixed>>|null
   */
  private ?array $cache = NULL;

  /**
   * @param iterable<\Drupal\mcp_tools\Mcp\Prompt\PromptProviderInterface> $providers
   *   Tagged prompt providers.
   */
  public function __construct(iterable $providers = []) {
    foreach ($providers as $provider) {
      $this->providers[] = $provider;
    }
  }

  /**
   * Returns all prompt definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Prompt definitions.
   */
  public function getPrompts(): array {
    if ($this->cache !== NULL) {
      return $this->cache;
    }

    $prompts = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->getPrompts() as $prompt) {
        $prompts[] = $prompt;
      }
    }

    $this->cache = $prompts;
    return $prompts;
  }

}
