<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Storage;

use CodeWheel\McpSecurity\ApiKey\Storage\StorageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;

/**
 * Drupal State API storage adapter for MCP HTTP Security.
 *
 * Bridges Drupal's State API to the framework-agnostic StorageInterface.
 */
final class DrupalStateStorage implements StorageInterface {

  private const STATE_KEY = 'mcp_tools_remote.api_keys';

  public function __construct(
    private readonly StateInterface $state,
    private readonly ?LockBackendInterface $lock = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAll(): array {
    $data = $this->state->get(self::STATE_KEY, []);
    return is_array($data) ? $data : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAll(array $keys): void {
    $this->state->set(self::STATE_KEY, $keys);
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $keyId): ?array {
    $all = $this->getAll();
    $record = $all[$keyId] ?? NULL;
    return is_array($record) ? $record : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $keyId, array $data): void {
    $this->locked(function () use ($keyId, $data): void {
      $all = $this->getAll();
      // Noting the use of a key that is gone would bring it back.
      if (!isset($all[$keyId]) && !empty($data['last_used'])) {
        return;
      }
      $all[$keyId] = $data;
      $this->setAll($all);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $keyId): bool {
    return $this->locked(function () use ($keyId): bool {
      $all = $this->getAll();
      if (!isset($all[$keyId])) {
        return FALSE;
      }
      unset($all[$keyId]);
      $this->setAll($all);
      return TRUE;
    });
  }

  /**
   * Reads the keys as they are now and changes them, one process at a time.
   *
   * All keys are one value. A request that wrote back the copy it had read
   * earlier undid a revocation made in between.
   */
  private function locked(callable $change): mixed {
    $name = 'mcp_tools_remote.api_keys';
    for ($tries = 0; $this->lock !== NULL && !$this->lock->acquire($name, 5); $tries++) {
      if ($tries === 5) {
        throw new \RuntimeException('The API keys are being changed by another process. Try again.');
      }
      $this->lock->wait($name, 1);
    }
    try {
      $this->state->resetCache();
      return $change();
    }
    finally {
      $this->lock?->release($name);
    }
  }

}
