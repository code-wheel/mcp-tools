<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\RoleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin to get role permissions.
 */
#[Tool(
  id: 'mcp_structure_get_role_permissions',
  label: new TranslatableMarkup('Get Role Permissions'),
  description: new TranslatableMarkup('Get all permissions granted to a role, grouped by module. Use this to understand what a role can do before modifying permissions.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup('Machine name of the role (e.g., "editor", "authenticated"). Use ListRoles to see available roles.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup('Machine name of the role.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the role.'),
    ),
    'is_admin' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Is Admin'),
      description: new TranslatableMarkup('TRUE if this role has all permissions (admin role). Permission list will be empty for admin roles.'),
    ),
    'permissions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions'),
      description: new TranslatableMarkup('Flat list of permission machine names. Use with GrantPermissions/RevokePermissions.'),
    ),
    'permissions_by_provider' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Permissions by Provider'),
      description: new TranslatableMarkup('Permissions grouped by module with id, title, description, and restrict_access flag. Helps understand what each permission does.'),
    ),
    'permission_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Permission Count'),
      description: new TranslatableMarkup('Total number of permissions granted to this role.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage this role in admin UI.'),
    ),
  ],
)]
class GetRolePermissions extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Role ID is required.'];
    }

    return $this->roleService->getRolePermissions($id);
  }

}
