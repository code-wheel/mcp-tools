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
  id: 'mcp_update_user',
  label: new TranslatableMarkup('Update User'),
  description: new TranslatableMarkup('Update an existing user\'s email, status, or roles. Cannot modify uid 1.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'uid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup('User ID to update. Get from GetUsers or CreateUser. Cannot modify uid 1.'),
      required: TRUE,
    ),
    'updates' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updates'),
      description: new TranslatableMarkup('Fields to update: mail, status (bool), roles (array). Only specified fields are changed.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'uid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup('The updated user ID.'),
    ),
    'username' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup('The username.'),
    ),
    'email' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Email'),
      description: new TranslatableMarkup('Current email address after update.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Current status: "active" or "blocked".'),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Current roles after update.'),
    ),
    'changed_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Changed Fields'),
      description: new TranslatableMarkup('List of fields that were actually modified.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class UpdateUser extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $uid = $input['uid'] ?? 0;
    $updates = $input['updates'] ?? [];

    if (empty($uid)) {
      return ['success' => FALSE, 'error' => 'User ID (uid) is required.'];
    }
    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    return $this->userService->updateUser((int) $uid, $updates);
  }


}
