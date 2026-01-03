<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_structure\Service\RoleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting user roles.
 *
 * @Tool(
 *   id = "mcp_structure_delete_role",
 *   label = @Translation("Delete Role"),
 *   description = @Translation("Delete a user role. Core roles cannot be deleted."),
 *   category = "structure",
 * )
 */
class DeleteRole extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected RoleService $roleService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->roleService = $container->get('mcp_tools_structure.role');
    return $instance;
  }

  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Role id is required.'];
    }

    return $this->roleService->deleteRole($id);
  }

  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Role ID', 'required' => TRUE],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Deleted Role ID'],
      'affected_users' => ['type' => 'integer', 'label' => 'Affected Users'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
