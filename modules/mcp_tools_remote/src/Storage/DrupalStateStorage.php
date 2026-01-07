<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote\Storage;

use CodeWheel\McpSecurity\ApiKey\Storage\StorageInterface;
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
    $all = $this->getAll();
    $all[$keyId] = $data;
    $this->setAll($all);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $keyId): bool {
    $all = $this->getAll();
    if (!isset($all[$keyId])) {
      return FALSE;
    }
    unset($all[$keyId]);
    $this->setAll($all);
    return TRUE;
  }

}
