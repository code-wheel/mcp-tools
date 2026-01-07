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
  id: 'mcp_structure_create_role',
  label: new TranslatableMarkup('Create Role'),
  description: new TranslatableMarkup('Create a new user role with optional initial permissions.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Lowercase, underscores (e.g., "content_editor")'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Content Editor")'),
      required: TRUE,
    ),
    'permissions' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions'),
      description: new TranslatableMarkup('Initial permissions to grant'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup('Machine name of the created role. Use with AssignUserRoles and GrantPermissions.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role Label'),
      description: new TranslatableMarkup('Human-readable role name.'),
    ),
    'permissions_granted' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions Granted'),
      description: new TranslatableMarkup('List of permissions that were successfully granted to the role.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class CreateRole extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->roleService->createRole($id, $label, $input['permissions'] ?? []);
  }


}
