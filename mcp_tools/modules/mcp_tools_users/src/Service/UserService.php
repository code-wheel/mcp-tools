<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\user\Entity\User;

/**
 * Service for user management operations.
 */
class UserService {

  /**
   * Roles that can NEVER be assigned via MCP.
   *
   * This is a security-critical list. These roles have elevated privileges
   * that should only be assigned through direct admin action.
   */
  protected const BLOCKED_ROLES = [
    'administrator',
    'admin',  // Common alternative name
  ];

  /**
   * Role patterns that are blocked (case-insensitive regex).
   *
   * Prevents bypass attempts via similar role names.
   */
  protected const BLOCKED_ROLE_PATTERNS = [
    '/^admin/i',  // Anything starting with "admin"
    '/administrator/i',  // Anything containing "administrator"
    '/^super/i',  // super_user, superadmin, etc.
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PasswordGeneratorInterface $passwordGenerator,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create a new user.
   *
   * @param string $username
   *   The username for the new user.
   * @param string $email
   *   The email address for the new user.
   * @param array $options
   *   Optional settings: password, roles, status.
   *
   * @return array
   *   Result array with success status and user data or error.
   */
  public function createUser(string $username, string $email, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Check if username already exists.
    $existingUser = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);
    if (!empty($existingUser)) {
      return ['success' => FALSE, 'error' => "Username '$username' already exists."];
    }

    // Check if email already exists.
    $existingEmail = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]);
    if (!empty($existingEmail)) {
      return ['success' => FALSE, 'error' => "Email '$email' is already in use."];
    }

    try {
      // Generate password if not provided.
      $password = $options['password'] ?? $this->passwordGenerator->generate(16);
      $passwordGenerated = !isset($options['password']);

      $userData = [
        'name' => $username,
        'mail' => $email,
        'pass' => $password,
        'status' => $options['status'] ?? 1,
      ];

      $user = User::create($userData);

      // Assign roles if provided (filter out 'administrator').
      if (!empty($options['roles'])) {
        $roles = $this->filterRoles($options['roles']);
        foreach ($roles as $role) {
          $user->addRole($role);
        }
      }

      $user->save();

      $this->auditLogger->logSuccess('create_user', 'user', (string) $user->id(), [
        'username' => $username,
        'email' => $email,
        'roles' => $options['roles'] ?? [],
      ]);

      $result = [
        'success' => TRUE,
        'data' => [
          'uid' => $user->id(),
          'uuid' => $user->uuid(),
          'username' => $username,
          'email' => $email,
          'status' => $user->isActive() ? 'active' : 'blocked',
          'roles' => $user->getRoles(TRUE),
          'message' => "User '$username' created successfully.",
        ],
      ];

      // Include generated password in response if auto-generated.
      if ($passwordGenerated) {
        $result['data']['generated_password'] = $password;
        $result['data']['password_note'] = 'This password was auto-generated. Store it securely or send a password reset email.';
      }

      return $result;
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_user', 'user', 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create user: ' . $e->getMessage()];
    }
  }

  /**
   * Update an existing user.
   *
   * @param int $uid
   *   The user ID to update.
   * @param array $updates
   *   Array of updates: email, status, roles.
   *
   * @return array
   *   Result array with success status and updated data or error.
   */
  public function updateUser(int $uid, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Protect uid 1 (super admin).
    if ($uid === 1) {
      return ['success' => FALSE, 'error' => 'Cannot modify the super admin user (uid 1).'];
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return ['success' => FALSE, 'error' => "User with ID $uid not found."];
    }

    try {
      $changedFields = [];

      // Update email.
      if (isset($updates['email'])) {
        // Check if email is already in use by another user.
        $existingEmail = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $updates['email']]);
        $existingEmail = reset($existingEmail);
        if ($existingEmail && $existingEmail->id() != $uid) {
          return ['success' => FALSE, 'error' => "Email '{$updates['email']}' is already in use."];
        }
        $user->setEmail($updates['email']);
        $changedFields[] = 'email';
      }

      // Update status.
      if (isset($updates['status'])) {
        $updates['status'] ? $user->activate() : $user->block();
        $changedFields[] = 'status';
      }

      // Update roles.
      if (isset($updates['roles'])) {
        $newRoles = $this->filterRoles($updates['roles']);
        $currentRoles = $user->getRoles(TRUE);

        // Remove roles not in the new list.
        foreach ($currentRoles as $role) {
          if (!in_array($role, $newRoles)) {
            $user->removeRole($role);
          }
        }

        // Add new roles.
        foreach ($newRoles as $role) {
          if (!in_array($role, $currentRoles)) {
            $user->addRole($role);
          }
        }
        $changedFields[] = 'roles';
      }

      $user->save();

      $this->auditLogger->logSuccess('update_user', 'user', (string) $uid, ['updates' => $changedFields]);

      return [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'email' => $user->getEmail(),
          'status' => $user->isActive() ? 'active' : 'blocked',
          'roles' => $user->getRoles(TRUE),
          'changed_fields' => $changedFields,
          'message' => "User updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_user', 'user', (string) $uid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update user: ' . $e->getMessage()];
    }
  }

  /**
   * Block a user.
   *
   * @param int $uid
   *   The user ID to block.
   *
   * @return array
   *   Result array with success status or error.
   */
  public function blockUser(int $uid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Protect uid 1 (super admin).
    if ($uid === 1) {
      return ['success' => FALSE, 'error' => 'Cannot block the super admin user (uid 1).'];
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return ['success' => FALSE, 'error' => "User with ID $uid not found."];
    }

    if (!$user->isActive()) {
      return [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'status' => 'blocked',
          'message' => 'User was already blocked.',
          'changed' => FALSE,
        ],
      ];
    }

    try {
      $user->block();
      $user->save();

      $this->auditLogger->logSuccess('block_user', 'user', (string) $uid, [
        'username' => $user->getAccountName(),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'status' => 'blocked',
          'message' => "User '{$user->getAccountName()}' has been blocked.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('block_user', 'user', (string) $uid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to block user: ' . $e->getMessage()];
    }
  }

  /**
   * Activate a blocked user.
   *
   * @param int $uid
   *   The user ID to activate.
   *
   * @return array
   *   Result array with success status or error.
   */
  public function activateUser(int $uid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return ['success' => FALSE, 'error' => "User with ID $uid not found."];
    }

    if ($user->isActive()) {
      return [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'status' => 'active',
          'message' => 'User was already active.',
          'changed' => FALSE,
        ],
      ];
    }

    try {
      $user->activate();
      $user->save();

      $this->auditLogger->logSuccess('activate_user', 'user', (string) $uid, [
        'username' => $user->getAccountName(),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'status' => 'active',
          'message' => "User '{$user->getAccountName()}' has been activated.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('activate_user', 'user', (string) $uid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to activate user: ' . $e->getMessage()];
    }
  }

  /**
   * Assign roles to a user.
   *
   * @param int $uid
   *   The user ID.
   * @param array $roles
   *   Array of role machine names to assign.
   *
   * @return array
   *   Result array with success status or error.
   */
  public function assignRoles(int $uid, array $roles): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return ['success' => FALSE, 'error' => "User with ID $uid not found."];
    }

    // Filter out 'administrator' role.
    $filteredRoles = $this->filterRoles($roles);
    $blockedRoles = array_diff($roles, $filteredRoles);

    try {
      $addedRoles = [];
      $existingRoles = $user->getRoles(TRUE);

      foreach ($filteredRoles as $role) {
        // Verify role exists.
        $roleEntity = $this->entityTypeManager->getStorage('user_role')->load($role);
        if (!$roleEntity) {
          continue;
        }

        if (!in_array($role, $existingRoles)) {
          $user->addRole($role);
          $addedRoles[] = $role;
        }
      }

      $user->save();

      $this->auditLogger->logSuccess('assign_roles', 'user', (string) $uid, [
        'username' => $user->getAccountName(),
        'added_roles' => $addedRoles,
      ]);

      $result = [
        'success' => TRUE,
        'data' => [
          'uid' => $uid,
          'username' => $user->getAccountName(),
          'roles' => $user->getRoles(TRUE),
          'added_roles' => $addedRoles,
          'message' => empty($addedRoles) ? 'No new roles were added.' : 'Roles assigned successfully.',
        ],
      ];

      if (!empty($blockedRoles)) {
        $result['data']['blocked_roles'] = $blockedRoles;
        $result['data']['blocked_roles_note'] = "The 'administrator' role cannot be assigned via MCP.";
      }

      return $result;
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('assign_roles', 'user', (string) $uid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to assign roles: ' . $e->getMessage()];
    }
  }

  /**
   * Filter out prohibited roles.
   *
   * SECURITY: This method uses multiple layers of protection:
   * 1. Explicit blocklist of known dangerous role names
   * 2. Pattern matching to catch variations (admin*, *administrator*, super*)
   * 3. Case-insensitive matching to prevent bypass via case variation
   *
   * @param array $roles
   *   Array of role machine names.
   *
   * @return array
   *   Filtered array of roles with blocked roles removed.
   */
  protected function filterRoles(array $roles): array {
    return array_filter($roles, function ($role) {
      // Normalize to lowercase for comparison.
      $roleLower = strtolower(trim($role));

      // Check against explicit blocklist.
      foreach (self::BLOCKED_ROLES as $blockedRole) {
        if ($roleLower === strtolower($blockedRole)) {
          return FALSE;
        }
      }

      // Check against blocked patterns.
      foreach (self::BLOCKED_ROLE_PATTERNS as $pattern) {
        if (preg_match($pattern, $role)) {
          return FALSE;
        }
      }

      return TRUE;
    });
  }

  /**
   * Check if a role is blocked from MCP assignment.
   *
   * @param string $role
   *   The role machine name.
   *
   * @return bool
   *   TRUE if the role is blocked.
   */
  public function isRoleBlocked(string $role): bool {
    $filtered = $this->filterRoles([$role]);
    return empty($filtered);
  }

}
