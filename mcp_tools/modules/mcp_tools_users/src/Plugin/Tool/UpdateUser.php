<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_users\Service\UserService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_update_user",
 *   label = @Translation("Update User"),
 *   description = @Translation("Update an existing user's email, status, or roles. Cannot modify uid 1."),
 *   category = "users",
 * )
 */
class UpdateUser extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  public function execute(array $input = []): array {
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

  public function getInputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID', 'required' => TRUE],
      'updates' => ['type' => 'object', 'label' => 'Updates', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID'],
      'username' => ['type' => 'string', 'label' => 'Username'],
      'email' => ['type' => 'string', 'label' => 'Email'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'roles' => ['type' => 'array', 'label' => 'Roles'],
      'changed_fields' => ['type' => 'array', 'label' => 'Changed Fields'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
