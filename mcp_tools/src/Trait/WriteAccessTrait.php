<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Trait;

use Drupal\mcp_tools\Service\AccessManager;

/**
 * Trait for write tools to check access control.
 *
 * Use this trait in Tool plugins that perform write operations.
 * Call checkWriteAccess() at the start of execute() to enforce access control.
 */
trait WriteAccessTrait {

  /**
   * The access manager service.
   */
  protected AccessManager $accessManager;

  /**
   * Check if write operations are allowed.
   *
   * @return array|null
   *   NULL if allowed, error array if denied.
   */
  protected function checkWriteAccess(): ?array {
    if (!isset($this->accessManager)) {
      $this->accessManager = \Drupal::service('mcp_tools.access_manager');
    }

    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    return NULL;
  }

  /**
   * Check if admin operations are allowed.
   *
   * @return array|null
   *   NULL if allowed, error array if denied.
   */
  protected function checkAdminAccess(): ?array {
    if (!isset($this->accessManager)) {
      $this->accessManager = \Drupal::service('mcp_tools.access_manager');
    }

    $access = $this->accessManager->checkWriteAccess('admin', 'admin');
    if (!$access['allowed']) {
      return [
        'success' => FALSE,
        'error' => $access['reason'],
        'code' => $access['code'] ?? 'ACCESS_DENIED',
        'retry_after' => $access['retry_after'] ?? NULL,
      ];
    }

    return NULL;
  }

  /**
   * Set the access manager (for dependency injection).
   *
   * @param \Drupal\mcp_tools\Service\AccessManager $accessManager
   *   The access manager service.
   */
  public function setAccessManager(AccessManager $accessManager): void {
    $this->accessManager = $accessManager;
  }

}
