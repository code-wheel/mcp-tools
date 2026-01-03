<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_assign_role",
 *   label = @Translation("Batch Assign Role to Users"),
 *   description = @Translation("Assign a role to multiple users at once. Maximum 50 users per operation."),
 *   category = "batch",
 * )
 */
class AssignRoleToUsers extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $role = $input['role'] ?? '';
    $userIds = $input['user_ids'] ?? [];

    if (empty($role)) {
      return ['success' => FALSE, 'error' => 'Role is required.'];
    }

    if (empty($userIds)) {
      return ['success' => FALSE, 'error' => 'User IDs array is required.'];
    }

    return $this->batchService->assignRoleToUsers($role, $userIds);
  }

  public function getInputDefinition(): array {
    return [
      'role' => [
        'type' => 'string',
        'label' => 'Role',
        'description' => 'The machine name of the role to assign (e.g., "editor", "content_editor"). Note: "administrator" role cannot be assigned.',
        'required' => TRUE,
      ],
      'user_ids' => [
        'type' => 'array',
        'label' => 'User IDs',
        'description' => 'Array of user IDs to assign the role to.',
        'required' => TRUE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'role' => ['type' => 'string', 'label' => 'Role'],
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'assigned_count' => ['type' => 'integer', 'label' => 'Assigned Count'],
      'already_had_count' => ['type' => 'integer', 'label' => 'Already Had Role Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'assigned' => ['type' => 'array', 'label' => 'Assigned Users'],
      'already_had' => ['type' => 'array', 'label' => 'Users Who Already Had Role'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
