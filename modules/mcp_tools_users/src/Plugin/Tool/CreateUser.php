<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_users\Service\UserService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_create_user",
 *   label = @Translation("Create User"),
 *   description = @Translation("Create a new user account with optional roles and auto-generated password."),
 *   category = "users",
 * )
 */
class CreateUser extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'username' => ['type' => 'string', 'label' => 'Username', 'required' => TRUE],
      'email' => ['type' => 'string', 'label' => 'Email', 'required' => TRUE],
      'password' => ['type' => 'string', 'label' => 'Password', 'required' => FALSE],
      'roles' => ['type' => 'array', 'label' => 'Roles', 'required' => FALSE],
      'status' => ['type' => 'boolean', 'label' => 'Active', 'required' => FALSE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID'],
      'uuid' => ['type' => 'string', 'label' => 'UUID'],
      'username' => ['type' => 'string', 'label' => 'Username'],
      'email' => ['type' => 'string', 'label' => 'Email'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'roles' => ['type' => 'array', 'label' => 'Roles'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
