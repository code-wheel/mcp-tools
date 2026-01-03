<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_users\Service\UserService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_assign_user_roles",
 *   label = @Translation("Assign User Roles"),
 *   description = @Translation("Assign roles to a user. The 'administrator' role is blocked."),
 *   category = "users",
 * )
 */
class AssignUserRoles extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID', 'required' => TRUE],
      'roles' => ['type' => 'array', 'label' => 'Roles', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID'],
      'username' => ['type' => 'string', 'label' => 'Username'],
      'roles' => ['type' => 'array', 'label' => 'Current Roles'],
      'added_roles' => ['type' => 'array', 'label' => 'Added Roles'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
