<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_remote\Service\ApiKeyManager::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class ApiKeyManagerTest extends UnitTestCase {

  private StateInterface $state;
  private PrivateKey $privateKey;
  private TimeInterface $time;
  private array $stateStorage = [];

  protected function setUp(): void {
    parent::setUp();

    $this->stateStorage = [];

    $this->state = $this->createMock(StateInterface::class);
    $this->state->method('get')
      ->willReturnCallback(function (string $key, mixed $default = NULL) {
        return $this->stateStorage[$key] ?? $default;
      });
    $this->state->method('set')
      ->willReturnCallback(function (string $key, mixed $value): void {
        $this->stateStorage[$key] = $value;
      });

    $this->privateKey = $this->createMock(PrivateKey::class);
    $this->privateKey->method('get')->willReturn('test-pepper');

    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getRequestTime')->willReturn(1700000000);
    $this->time->method('getCurrentTime')->willReturn(1700000000);
  }

  public function testCreateValidateAndList(): void {
    $manager = new ApiKeyManager($this->state, $this->privateKey, $this->time);

    $created = $manager->createKey('Test Key', ['read']);
    $this->assertArrayHasKey('key_id', $created);
    $this->assertArrayHasKey('api_key', $created);

    $validated = $manager->validate($created['api_key']);
    $this->assertNotNull($validated);
    $this->assertSame($created['key_id'], $validated['key_id']);
    $this->assertSame('Test Key', $validated['label']);
    $this->assertSame(['read'], $validated['scopes']);

    $keys = $manager->listKeys();
    $this->assertArrayHasKey($created['key_id'], $keys);
    $this->assertSame('Test Key', $keys[$created['key_id']]['label']);
    $this->assertSame(['read'], $keys[$created['key_id']]['scopes']);
    $this->assertSame(1700000000, $keys[$created['key_id']]['created']);
    $this->assertSame(1700000000, $keys[$created['key_id']]['last_used']);
    $this->assertNull($keys[$created['key_id']]['expires']);
  }

  public function testValidateRejectsExpiredKeys(): void {
    $now = 1700000000;
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(static function () use (&$now): int {
      return $now;
    });
    $time->method('getCurrentTime')->willReturnCallback(static function () use (&$now): int {
      return $now;
    });

    $manager = new ApiKeyManager($this->state, $this->privateKey, $time);

    $created = $manager->createKey('Expiring', ['read'], 10);
    $this->assertNotNull($manager->validate($created['api_key']));

    $now = 1700000011;
    $this->assertNull($manager->validate($created['api_key']));
  }

  public function testValidateRejectsInvalidKeys(): void {
    $manager = new ApiKeyManager($this->state, $this->privateKey, $this->time);

    $this->assertNull($manager->validate(''));
    $this->assertNull($manager->validate('not-a-key'));
    $this->assertNull($manager->validate('mcp_tools.badformat'));

    $created = $manager->createKey('Test', ['read']);
    $this->assertNotNull($manager->validate($created['api_key']));

    // Wrong prefix.
    $badPrefix = preg_replace('/^mcp_tools\\./', 'other.', $created['api_key']);
    $this->assertNull($manager->validate((string) $badPrefix));

    // Wrong secret.
    $parts = explode('.', $created['api_key'], 3);
    $this->assertCount(3, $parts);
    $badSecret = $parts[0] . '.' . $parts[1] . '.wrong';
    $this->assertNull($manager->validate($badSecret));
  }

  public function testRevokeKey(): void {
    $manager = new ApiKeyManager($this->state, $this->privateKey, $this->time);

    $created = $manager->createKey('Test', ['read']);
    $this->assertTrue($manager->revokeKey($created['key_id']));
    $this->assertFalse($manager->revokeKey($created['key_id']));
    $this->assertNull($manager->validate($created['api_key']));
  }

}
