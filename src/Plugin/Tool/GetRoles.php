<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\UserAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting user roles.
 *
 * @Tool(
 *   id = "mcp_tools_get_roles",
 *   label = @Translation("Get Roles"),
 *   description = @Translation("Get all user roles with their permissions."),
 *   category = "users",
 * )
 */
class GetRoles extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return [
      'success' => TRUE,
      'data' => $this->userAnalysis->getRoles(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_roles' => [
        'type' => 'integer',
        'label' => 'Total Roles',
      ],
      'roles' => [
        'type' => 'list',
        'label' => 'Roles',
      ],
    ];
  }

}
