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
      description: new TranslatableMarkup('Permissions to grant, by their names, e.g. ["access content", "create article content"]. mcp_structure_get_role_permissions shows the names a role has. An unknown one is refused.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role ID'),
      description: new TranslatableMarkup('Machine name of the created role. Use with mcp_assign_user_roles and mcp_structure_grant_permissions.'),
    ),
    'label' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role Label'),
      description: new TranslatableMarkup('Human-readable role name.'),
    ),
    'permissions_granted' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Permissions Granted'),
      description: new TranslatableMarkup('List of permissions that were successfully granted to the role.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class CreateRole extends McpToolsToolBase implements AsksFirstInterface {

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
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->roleService->createRole($id, $label, $input['permissions'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function reasonToAsk(array $arguments): ?string {
    $role = $arguments['id'] ?? '';
    $permissions = $arguments['permissions'] ?? [];
    if (!is_string($role) || $role === '' || !is_array($permissions)) {
      return NULL;
    }
    $restricted = $this->roleService->restrictedToGrant($permissions, $role, TRUE);
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
