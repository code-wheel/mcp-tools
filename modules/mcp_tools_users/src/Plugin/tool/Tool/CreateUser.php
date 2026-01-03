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
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'email' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Email'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'password' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Password'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'roles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Active'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'uid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup(''),
    ),
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('UUID'),
      description: new TranslatableMarkup(''),
    ),
    'username' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup(''),
    ),
    'email' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Email'),
      description: new TranslatableMarkup(''),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup(''),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class CreateUser extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

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
