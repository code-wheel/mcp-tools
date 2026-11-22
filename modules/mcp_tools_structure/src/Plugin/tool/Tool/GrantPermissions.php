<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\RoleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\AsksFirstInterface;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_structure_grant_permissions',
  label: new TranslatableMarkup('Grant Permissions'),
  description: new TranslatableMarkup('Grant permissions to a user role. Permissions for trusted roles only need the admin scope. The permissions of MCP Tools itself are blocked.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'role' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup('Role machine name. Use mcp_tools_get_roles to see available roles.'),
      required: TRUE,
    ),
    'permissions' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions'),
      description: new TranslatableMarkup('Array of permission machine names. Use mcp_tools_get_permissions to see available permissions.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'role' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role'),
      description: new TranslatableMarkup('The role that was modified.'),
    ),
    'granted' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions Granted'),
      description: new TranslatableMarkup('Permissions that were newly granted.'),
    ),
    'already_had' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Already Had'),
      description: new TranslatableMarkup('Permissions the role already had (not modified).'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class GrantPermissions extends McpToolsToolBase implements AsksFirstInterface {

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
    $role = $input['role'] ?? '';
    $permissions = $input['permissions'] ?? [];

    if (empty($role)) {
      return ['success' => FALSE, 'error' => 'Role is required.'];
    }

    if (empty($permissions)) {
      return ['success' => FALSE, 'error' => 'At least one permission is required.'];
    }

    return $this->roleService->grantPermissions($role, $permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function reasonToAsk(array $arguments): ?string {
    $role = $arguments['role'] ?? '';
    $permissions = $arguments['permissions'] ?? [];
    if (!is_string($role) || $role === '' || !is_array($permissions)) {
      return NULL;
    }
    $restricted = $this->roleService->restrictedToGrant($permissions, $role, FALSE);
    if ($restricted === []) {
      return NULL;
    }
    return sprintf(
      'The role "%s" would get %s that Drupal marks for trusted roles only: %s.',
      $role,
      count($restricted) === 1 ? 'a permission' : 'permissions',
      implode(', ', $restricted),
    );
  }

}
