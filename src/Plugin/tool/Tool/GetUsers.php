<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\UserAnalysisService;
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
  id: 'mcp_tools_get_users',
  label: new TranslatableMarkup('Get Users'),
  description: new TranslatableMarkup('Get user accounts with status, roles, and activity information.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum users to return. Max 100.'),
      required: FALSE,
      default_value: 50,
    ),
    'role' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Role Filter'),
      description: new TranslatableMarkup('Filter by role ID (e.g., "administrator", "editor").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total_users' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Users'),
      description: new TranslatableMarkup('Total number of user accounts on the site.'),
    ),
    'active_users' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Active Users'),
      description: new TranslatableMarkup('Number of non-blocked user accounts.'),
    ),
    'blocked_users' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Blocked Users'),
      description: new TranslatableMarkup('Number of blocked user accounts.'),
    ),
    'users' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('User List'),
      description: new TranslatableMarkup('Array of users with uid, name, mail, status (1=active, 0=blocked), roles, created, and last_access. Use uid to update/block users.'),
    ),
  ],
)]
class GetUsers extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  protected UserAnalysisService $userAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userAnalysis = $container->get('mcp_tools.user_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $limit = min($input['limit'] ?? 50, 100);
    $role = $input['role'] ?? NULL;

    return [
      'success' => TRUE,
      'data' => $this->userAnalysis->getUsers($limit, $role),
    ];
  }

  

  

}
