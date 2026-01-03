<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\tool\Tool;

use Drupal\mcp_tools_users\Service\UserService;
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
  id: 'mcp_assign_user_roles',
  label: new TranslatableMarkup('Assign User Roles'),
  description: new TranslatableMarkup('Assign roles to a user. The \'administrator\' role is blocked.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'uid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'roles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'uid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup(''),
    ),
    'username' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup(''),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Current Roles'),
      description: new TranslatableMarkup(''),
    ),
    'added_roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Added Roles'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class AssignUserRoles extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $uid = $input['uid'] ?? 0;
    $roles = $input['roles'] ?? [];

    if (empty($uid)) {
      return ['success' => FALSE, 'error' => 'User ID (uid) is required.'];
    }
    if (empty($roles)) {
      return ['success' => FALSE, 'error' => 'At least one role is required.'];
    }

    return $this->userService->assignRoles((int) $uid, $roles);
  }


}
