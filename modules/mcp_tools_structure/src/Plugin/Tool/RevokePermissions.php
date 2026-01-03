<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\RoleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for revoking permissions from roles.
 *
 * @Tool(
 *   id = "mcp_structure_revoke_permissions",
 *   label = @Translation("Revoke Permissions"),
 *   description = @Translation("Revoke permissions from a user role."),
 *   category = "structure",
 * )
 */
class RevokePermissions extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  public function execute(array $input = []): array {
    $role = $input['role'] ?? '';
    $permissions = $input['permissions'] ?? [];

    if (empty($role)) {
      return ['success' => FALSE, 'error' => 'Role is required.'];
    }

    if (empty($permissions)) {
      return ['success' => FALSE, 'error' => 'At least one permission is required.'];
    }

    return $this->roleService->revokePermissions($role, $permissions);
  }

  public function getInputDefinition(): array {
    return [
      'role' => ['type' => 'string', 'label' => 'Role ID', 'required' => TRUE],
      'permissions' => ['type' => 'list', 'label' => 'Permissions', 'required' => TRUE, 'description' => 'Permissions to revoke'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'role' => ['type' => 'string', 'label' => 'Role'],
      'revoked' => ['type' => 'list', 'label' => 'Permissions Revoked'],
      'didnt_have' => ['type' => 'list', 'label' => 'Did Not Have'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
