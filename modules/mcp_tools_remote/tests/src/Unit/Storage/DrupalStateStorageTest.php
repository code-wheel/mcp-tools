<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Storage;

use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools_remote\Storage\DrupalStateStorage;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DrupalStateStorage.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_remote\Storage\DrupalStateStorage::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class DrupalStateStorageTest extends TestCase {

  private StateInterface $state;
  private DrupalStateStorage $storage;

  protected function setUp(): void {
    parent::setUp();
    $this->state = $this->createMock(StateInterface::class);
    $this->storage = new DrupalStateStorage($this->state);
  }

  public function testGetAllReturnsEmptyArrayWhenNoData(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn([]);

    $result = $this->storage->getAll();

    $this->assertSame([], $result);
  }

  public function testGetAllReturnsStoredKeys(): void {
    $keys = [
      'key1' => ['name' => 'Test Key 1', 'hash' => 'abc123'],
      'key2' => ['name' => 'Test Key 2', 'hash' => 'def456'],
    ];

    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn($keys);

    $result = $this->storage->getAll();

    $this->assertSame($keys, $result);
  }

  public function testGetAllHandlesInvalidData(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn('invalid');

    $result = $this->storage->getAll();

    $this->assertSame([], $result);
  }

  public function testSetAllStoresKeys(): void {
    $keys = [
      'key1' => ['name' => 'Test Key', 'hash' => 'abc123'],
    ];

    $this->state->expects($this->once())
      ->method('set')
      ->with('mcp_tools_remote.api_keys', $keys);

    $this->storage->setAll($keys);
  }

  public function testGetReturnsKeyData(): void {
    $keys = [
      'key1' => ['name' => 'Test Key', 'hash' => 'abc123'],
    ];

    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn($keys);

    $result = $this->storage->get('key1');

    $this->assertSame(['name' => 'Test Key', 'hash' => 'abc123'], $result);
  }

  public function testGetReturnsNullForMissingKey(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn([]);

    $result = $this->storage->get('nonexistent');

    $this->assertNull($result);
  }

  public function testGetReturnsNullForInvalidRecord(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn(['key1' => 'invalid_string']);

    $result = $this->storage->get('key1');

    $this->assertNull($result);
  }

  public function testSetAddsNewKey(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn([]);

    $this->state->expects($this->once())
      ->method('set')
      ->with('mcp_tools_remote.api_keys', [
        'key1' => ['name' => 'New Key', 'hash' => 'abc123'],
      ]);

    $this->storage->set('key1', ['name' => 'New Key', 'hash' => 'abc123']);
  }

  public function testSetUpdatesExistingKey(): void {
    $existingKeys = [
      'key1' => ['name' => 'Old Name', 'hash' => 'old123'],
    ];

    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn($existingKeys);

    $this->state->expects($this->once())
      ->method('set')
      ->with('mcp_tools_remote.api_keys', [
        'key1' => ['name' => 'New Name', 'hash' => 'new456'],
      ]);

    $this->storage->set('key1', ['name' => 'New Name', 'hash' => 'new456']);
  }

  public function testDeleteRemovesExistingKey(): void {
    $existingKeys = [
      'key1' => ['name' => 'Test Key', 'hash' => 'abc123'],
      'key2' => ['name' => 'Keep Key', 'hash' => 'def456'],
    ];

    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn($existingKeys);

    $this->state->expects($this->once())
      ->method('set')
      ->with('mcp_tools_remote.api_keys', [
        'key2' => ['name' => 'Keep Key', 'hash' => 'def456'],
      ]);

    $result = $this->storage->delete('key1');

    $this->assertTrue($result);
  }

  public function testDeleteReturnsFalseForMissingKey(): void {
    $this->state->method('get')
      ->with('mcp_tools_remote.api_keys', [])
      ->willReturn([]);

    $this->state->expects($this->never())->method('set');

    $result = $this->storage->delete('nonexistent');

    $this->assertFalse($result);
  }

}
