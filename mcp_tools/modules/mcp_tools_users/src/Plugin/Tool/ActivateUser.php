<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_users\Service\UserService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_activate_user",
 *   label = @Translation("Activate User"),
 *   description = @Translation("Activate a blocked user account."),
 *   category = "users",
 * )
 */
class ActivateUser extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected UserService $userService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  public function execute(array $input = []): array {
    $uid = $input['uid'] ?? 0;

    if (empty($uid)) {
      return ['success' => FALSE, 'error' => 'User ID (uid) is required.'];
    }

    return $this->userService->activateUser((int) $uid);
  }

  public function getInputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'uid' => ['type' => 'integer', 'label' => 'User ID'],
      'username' => ['type' => 'string', 'label' => 'Username'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'changed' => ['type' => 'boolean', 'label' => 'Changed'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
