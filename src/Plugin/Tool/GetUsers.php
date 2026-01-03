<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\UserAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting users.
 *
 * @Tool(
 *   id = "mcp_tools_get_users",
 *   label = @Translation("Get Users"),
 *   description = @Translation("Get user accounts with status, roles, and activity information."),
 *   category = "users",
 * )
 */
class GetUsers extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected UserAnalysisService $userAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->userAnalysis = $container->get('mcp_tools.user_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $limit = min($input['limit'] ?? 50, 100);
    $role = $input['role'] ?? NULL;

    return [
      'success' => TRUE,
      'data' => $this->userAnalysis->getUsers($limit, $role),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum users to return. Max 100.',
        'required' => FALSE,
        'default' => 50,
      ],
      'role' => [
        'type' => 'string',
        'label' => 'Role Filter',
        'description' => 'Filter by role ID (e.g., "administrator", "editor").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_users' => [
        'type' => 'integer',
        'label' => 'Total Users',
      ],
      'active_users' => [
        'type' => 'integer',
        'label' => 'Active Users',
      ],
      'blocked_users' => [
        'type' => 'integer',
        'label' => 'Blocked Users',
      ],
      'users' => [
        'type' => 'list',
        'label' => 'User List',
      ],
    ];
  }

}
