<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Resource\ResourceProviderInterface;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ResourceRegistry.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\Resource\ResourceRegistry::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ResourceRegistryTest extends TestCase {

  public function testGetResourcesWithNoProviders(): void {
    $registry = new ResourceRegistry([]);

    $resources = $registry->getResources();

    $this->assertSame([], $resources);
  }

  public function testGetResourcesCollectsFromAllProviders(): void {
    $provider1 = $this->createMock(ResourceProviderInterface::class);
    $provider1->method('getResources')->willReturn([
      ['uri' => 'drupal://resource1', 'name' => 'Resource 1'],
    ]);
    $provider1->method('getResourceTemplates')->willReturn([]);

    $provider2 = $this->createMock(ResourceProviderInterface::class);
    $provider2->method('getResources')->willReturn([
      ['uri' => 'drupal://resource2', 'name' => 'Resource 2'],
      ['uri' => 'drupal://resource3', 'name' => 'Resource 3'],
    ]);
    $provider2->method('getResourceTemplates')->willReturn([]);

    $registry = new ResourceRegistry([$provider1, $provider2]);

    $resources = $registry->getResources();

    $this->assertCount(3, $resources);
    $this->assertSame('drupal://resource1', $resources[0]['uri']);
    $this->assertSame('drupal://resource2', $resources[1]['uri']);
    $this->assertSame('drupal://resource3', $resources[2]['uri']);
  }

  public function testGetResourcesCachesResults(): void {
    $provider = $this->createMock(ResourceProviderInterface::class);
    $provider->expects($this->once())
      ->method('getResources')
      ->willReturn([['uri' => 'drupal://cached']]);
    $provider->method('getResourceTemplates')->willReturn([]);

    $registry = new ResourceRegistry([$provider]);

    // First call populates cache.
    $first = $registry->getResources();
    // Second call uses cache.
    $second = $registry->getResources();

    $this->assertSame($first, $second);
  }

  public function testGetResourceTemplatesWithNoProviders(): void {
    $registry = new ResourceRegistry([]);

    $templates = $registry->getResourceTemplates();

    $this->assertSame([], $templates);
  }

  public function testGetResourceTemplatesCollectsFromAllProviders(): void {
    $provider1 = $this->createMock(ResourceProviderInterface::class);
    $provider1->method('getResources')->willReturn([]);
    $provider1->method('getResourceTemplates')->willReturn([
      ['uriTemplate' => 'drupal://node/{id}', 'name' => 'Node Template'],
    ]);

    $provider2 = $this->createMock(ResourceProviderInterface::class);
    $provider2->method('getResources')->willReturn([]);
    $provider2->method('getResourceTemplates')->willReturn([
      ['uriTemplate' => 'drupal://user/{id}', 'name' => 'User Template'],
    ]);

    $registry = new ResourceRegistry([$provider1, $provider2]);

    $templates = $registry->getResourceTemplates();

    $this->assertCount(2, $templates);
    $this->assertSame('drupal://node/{id}', $templates[0]['uriTemplate']);
    $this->assertSame('drupal://user/{id}', $templates[1]['uriTemplate']);
  }

  public function testGetResourceTemplatesCachesResults(): void {
    $provider = $this->createMock(ResourceProviderInterface::class);
    $provider->method('getResources')->willReturn([]);
    $provider->expects($this->once())
      ->method('getResourceTemplates')
      ->willReturn([['uriTemplate' => 'drupal://cached/{id}']]);

    $registry = new ResourceRegistry([$provider]);

    // First call populates cache.
    $first = $registry->getResourceTemplates();
    // Second call uses cache.
    $second = $registry->getResourceTemplates();

    $this->assertSame($first, $second);
  }

  public function testResourcesAndTemplatesCacheIndependently(): void {
    $provider = $this->createMock(ResourceProviderInterface::class);
    $provider->expects($this->once())
      ->method('getResources')
      ->willReturn([['uri' => 'drupal://resource']]);
    $provider->expects($this->once())
      ->method('getResourceTemplates')
      ->willReturn([['uriTemplate' => 'drupal://template/{id}']]);

    $registry = new ResourceRegistry([$provider]);

    // Call each twice to verify independent caching.
    $registry->getResources();
    $registry->getResources();
    $registry->getResourceTemplates();
    $registry->getResourceTemplates();

    // Each method only called provider once (asserted by expects).
  }

  public function testWithIterableProviders(): void {
    $provider = $this->createMock(ResourceProviderInterface::class);
    $provider->method('getResources')->willReturn([
      ['uri' => 'drupal://iterable'],
    ]);
    $provider->method('getResourceTemplates')->willReturn([]);

    // Test with generator.
    $generator = (function () use ($provider): \Generator {
      yield $provider;
    })();

    $registry = new ResourceRegistry($generator);

    $resources = $registry->getResources();

    $this->assertCount(1, $resources);
    $this->assertSame('drupal://iterable', $resources[0]['uri']);
  }

}
