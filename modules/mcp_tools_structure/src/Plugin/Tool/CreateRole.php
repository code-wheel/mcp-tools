<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\RoleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating user roles.
 *
 * @Tool(
 *   id = "mcp_structure_create_role",
 *   label = @Translation("Create Role"),
 *   description = @Translation("Create a new user role with optional initial permissions."),
 *   category = "structure",
 * )
 */
class CreateRole extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->roleService->createRole($id, $label, $input['permissions'] ?? []);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Machine Name', 'required' => TRUE, 'description' => 'Lowercase, underscores (e.g., "content_editor")'],
      'label' => ['type' => 'string', 'label' => 'Label', 'required' => TRUE, 'description' => 'Human-readable name (e.g., "Content Editor")'],
      'permissions' => ['type' => 'list', 'label' => 'Permissions', 'required' => FALSE, 'description' => 'Initial permissions to grant'],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Role ID'],
      'label' => ['type' => 'string', 'label' => 'Role Label'],
      'permissions_granted' => ['type' => 'list', 'label' => 'Permissions Granted'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
