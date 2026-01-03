<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\tool\Tool;

use Drupal\mcp_tools_batch\Service\BatchService;
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
  id: 'mcp_batch_assign_role',
  label: new TranslatableMarkup('Batch Assign Role to Users'),
  description: new TranslatableMarkup('Assign a role to multiple users at once. Maximum 50 users per operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'role' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role'),
      description: new TranslatableMarkup('The machine name of the role to assign (e.g., "editor", "content_editor"). Note: "administrator" role cannot be assigned.'),
      required: TRUE,
    ),
    'user_ids' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('User IDs'),
      description: new TranslatableMarkup('Array of user IDs to assign the role to.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'role' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role'),
      description: new TranslatableMarkup(''),
    ),
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup(''),
    ),
    'assigned_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Assigned Count'),
      description: new TranslatableMarkup(''),
    ),
    'already_had_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Already Had Role Count'),
      description: new TranslatableMarkup(''),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup(''),
    ),
    'assigned' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Assigned Users'),
      description: new TranslatableMarkup(''),
    ),
    'already_had' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Users Who Already Had Role'),
      description: new TranslatableMarkup(''),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class AssignRoleToUsers extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'batch';


  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
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


}
