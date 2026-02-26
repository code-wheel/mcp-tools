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

/**
 * Tool plugin to list all roles.
 */
#[Tool(
  id: 'mcp_structure_list_roles',
  label: new TranslatableMarkup('List Roles'),
  description: new TranslatableMarkup('List all user roles with permission counts and user counts. Use this to understand the access control structure before modifying permissions.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Array of roles with id, label, weight, is_admin, permission_count, and user_count. Use id with GetRolePermissions or GrantPermissions.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Roles'),
      description: new TranslatableMarkup('Total number of roles in the system.'),
    ),
  ],
)]
class ListRoles extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  /**
   * The role service.
   *
   * @var \Drupal\mcp_tools_structure\Service\RoleService
   */
  protected RoleService $roleService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->roleService->listRoles();
  }

}
