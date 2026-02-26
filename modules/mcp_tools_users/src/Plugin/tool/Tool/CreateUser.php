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
  id: 'mcp_create_user',
  label: new TranslatableMarkup('Create User'),
  description: new TranslatableMarkup('Create a new user account with optional roles and auto-generated password.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'username' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup('Unique username for the account.'),
      required: TRUE,
    ),
    'email' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Email'),
      description: new TranslatableMarkup('Valid email address. Must be unique.'),
      required: TRUE,
    ),
    'password' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Password'),
      description: new TranslatableMarkup('Password for the account. If omitted, a random password is generated.'),
      required: FALSE,
    ),
    'roles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Array of role machine names to assign. The "administrator" role is blocked.'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Active'),
      description: new TranslatableMarkup('True to create active account, false to create blocked. Defaults to true.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'uid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup('ID of the created user. Use for updates, role assignments, etc.'),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup('Universally unique identifier for the user.'),
    ),
    'username' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup('The created username.'),
    ),
    'email' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Email'),
      description: new TranslatableMarkup('The user email address.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Account status: "active" or "blocked".'),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Roles assigned to the user.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class CreateUser extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  /**
   * The user service.
   *
   * @var \Drupal\mcp_tools_users\Service\UserService
   */
  protected UserService $userService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';

    if (empty($username) || empty($email)) {
      return ['success' => FALSE, 'error' => 'Both username and email are required.'];
    }

    $options = [];
    if (isset($input['password'])) {
      $options['password'] = $input['password'];
    }
    if (isset($input['roles'])) {
      $options['roles'] = $input['roles'];
    }
    if (isset($input['status'])) {
      $options['status'] = (bool) $input['status'];
    }

    return $this->userService->createUser($username, $email, $options);
  }

}
