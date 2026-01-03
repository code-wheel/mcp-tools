<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\UserAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting all available permissions.
 *
 * @Tool(
 *   id = "mcp_tools_get_permissions",
 *   label = @Translation("Get Permissions"),
 *   description = @Translation("Get all available permissions grouped by provider module."),
 *   category = "users",
 * )
 */
class GetPermissions extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
      'data' => $this->userAnalysis->getPermissions(),
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
      'total_permissions' => [
        'type' => 'integer',
        'label' => 'Total Permissions',
      ],
      'providers' => [
        'type' => 'integer',
        'label' => 'Provider Count',
      ],
      'by_provider' => [
        'type' => 'map',
        'label' => 'Permissions by Provider',
      ],
    ];
  }

}
