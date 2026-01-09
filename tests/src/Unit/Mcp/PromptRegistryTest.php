<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Prompt\PromptProviderInterface;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PromptRegistry.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\Prompt\PromptRegistry::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class PromptRegistryTest extends TestCase {

  public function testGetPromptsWithNoProviders(): void {
    $registry = new PromptRegistry([]);

    $prompts = $registry->getPrompts();

    $this->assertSame([], $prompts);
  }

  public function testGetPromptsCollectsFromAllProviders(): void {
    $provider1 = $this->createMock(PromptProviderInterface::class);
    $provider1->method('getPrompts')->willReturn([
      ['name' => 'prompt1', 'description' => 'First prompt'],
    ]);

    $provider2 = $this->createMock(PromptProviderInterface::class);
    $provider2->method('getPrompts')->willReturn([
      ['name' => 'prompt2', 'description' => 'Second prompt'],
      ['name' => 'prompt3', 'description' => 'Third prompt'],
    ]);

    $registry = new PromptRegistry([$provider1, $provider2]);

    $prompts = $registry->getPrompts();

    $this->assertCount(3, $prompts);
    $this->assertSame('prompt1', $prompts[0]['name']);
    $this->assertSame('prompt2', $prompts[1]['name']);
    $this->assertSame('prompt3', $prompts[2]['name']);
  }

  public function testGetPromptsCachesResults(): void {
    $provider = $this->createMock(PromptProviderInterface::class);
    $provider->expects($this->once())
      ->method('getPrompts')
      ->willReturn([['name' => 'cached_prompt']]);

    $registry = new PromptRegistry([$provider]);

    // First call populates cache.
    $first = $registry->getPrompts();
    // Second call uses cache (provider only called once).
    $second = $registry->getPrompts();

    $this->assertSame($first, $second);
  }

  public function testGetPromptsWithIterableProviders(): void {
    $provider = $this->createMock(PromptProviderInterface::class);
    $provider->method('getPrompts')->willReturn([
      ['name' => 'iterable_prompt'],
    ]);

    // Test with generator.
    $generator = (function () use ($provider): \Generator {
      yield $provider;
    })();

    $registry = new PromptRegistry($generator);

    $prompts = $registry->getPrompts();

    $this->assertCount(1, $prompts);
    $this->assertSame('iterable_prompt', $prompts[0]['name']);
  }

}
