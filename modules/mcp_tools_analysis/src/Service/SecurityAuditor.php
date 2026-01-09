<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service for security auditing.
 */
class SecurityAuditor {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Perform security audit.
   *
   * @return array
   *   Security audit results.
   */
  public function securityAudit(): array {
    $issues = [];
    $warnings = [];

    try {
      // Check anonymous user permissions.
      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      $anonymousRole = $roleStorage->load('anonymous');
      if ($anonymousRole) {
        $anonPermissions = $anonymousRole->getPermissions();
        $dangerousPerms = [
          'administer nodes',
          'administer users',
          'administer site configuration',
          'administer modules',
          'administer permissions',
          'bypass node access',
          'administer content types',
        ];

        $exposedPerms = array_intersect($anonPermissions, $dangerousPerms);
        if (!empty($exposedPerms)) {
          $issues[] = [
            'type' => 'dangerous_anonymous_permissions',
            'severity' => 'critical',
            'message' => 'Anonymous users have dangerous permissions: ' . implode(', ', $exposedPerms),
          ];
        }
      }

      // Check authenticated user permissions.
      $authenticatedRole = $roleStorage->load('authenticated');
      if ($authenticatedRole) {
        $authPermissions = $authenticatedRole->getPermissions();
        $sensitivePerms = [
          'administer users',
          'administer permissions',
          'administer site configuration',
        ];

        $exposedPerms = array_intersect($authPermissions, $sensitivePerms);
        if (!empty($exposedPerms)) {
          $warnings[] = [
            'type' => 'sensitive_authenticated_permissions',
            'severity' => 'warning',
            'message' => 'All authenticated users have sensitive permissions: ' . implode(', ', $exposedPerms),
          ];
        }
      }

      // Check for overly permissive roles.
      $roles = $roleStorage->loadMultiple();
      $overlyPermissiveRoles = [];
      foreach ($roles as $role) {
        if (in_array($role->id(), ['anonymous', 'authenticated', 'administrator'])) {
          continue;
        }
        $permissions = $role->getPermissions();
        if (in_array('bypass node access', $permissions) || in_array('administer permissions', $permissions)) {
          $overlyPermissiveRoles[] = $role->label();
        }
      }

      if (!empty($overlyPermissiveRoles)) {
        $warnings[] = [
          'type' => 'overly_permissive_roles',
          'severity' => 'warning',
          'message' => 'Roles with elevated permissions: ' . implode(', ', $overlyPermissiveRoles),
        ];
      }

      // Check user registration settings.
      $userSettings = $this->configFactory->get('user.settings');
      $registerMode = $userSettings->get('register');
      if ($registerMode === 'visitors') {
        $warnings[] = [
          'type' => 'open_registration',
          'severity' => 'warning',
          'message' => 'User registration is open to visitors without admin approval.',
        ];
      }

      // Check for users with admin role.
      $userStorage = $this->entityTypeManager->getStorage('user');
      $adminUsers = $userStorage->getQuery()
        ->condition('roles', 'administrator')
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->execute();

      if (count($adminUsers) > 5) {
        $warnings[] = [
          'type' => 'many_admins',
          'severity' => 'info',
          'message' => 'There are ' . count($adminUsers) . ' active administrator accounts. Review if all need admin access.',
        ];
      }

      // Check for blocked users with admin role.
      $blockedAdmins = $userStorage->getQuery()
        ->condition('roles', 'administrator')
        ->condition('status', 0)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($blockedAdmins)) {
        $warnings[] = [
          'type' => 'blocked_admins',
          'severity' => 'info',
          'message' => count($blockedAdmins) . ' blocked user(s) still have administrator role assigned.',
        ];
      }

      // Check PHP input format availability.
      if ($this->moduleHandler->moduleExists('php')) {
        $issues[] = [
          'type' => 'php_module_enabled',
          'severity' => 'critical',
          'message' => 'PHP Filter module is enabled. This is a serious security risk.',
        ];
      }

      $suggestions = [];
      if (!empty($issues)) {
        $suggestions[] = 'Address critical security issues immediately.';
      }
      if (!empty($warnings)) {
        $suggestions[] = 'Review permission assignments and apply principle of least privilege.';
      }
      $suggestions[] = 'Regularly audit user accounts and remove unused ones.';
      $suggestions[] = 'Enable two-factor authentication for admin accounts if available.';

      return [
        'success' => TRUE,
        'data' => [
          'critical_issues' => $issues,
          'warnings' => $warnings,
          'critical_count' => count($issues),
          'warning_count' => count($warnings),
          'admin_user_count' => count($adminUsers),
          'registration_mode' => $registerMode,
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to perform security audit: ' . $e->getMessage()];
    }
  }

}
