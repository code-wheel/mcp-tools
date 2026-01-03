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
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_structure_grant_permissions',
  label: new TranslatableMarkup('Grant Permissions'),
  description: new TranslatableMarkup('Grant permissions to a user role. Some dangerous permissions are blocked.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'role' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'permissions' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions'),
      description: new TranslatableMarkup('Permissions to grant'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'role' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role'),
      description: new TranslatableMarkup(''),
    ),
    'granted' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions Granted'),
      description: new TranslatableMarkup(''),
    ),
    'already_had' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Already Had'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GrantPermissions extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

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


}
