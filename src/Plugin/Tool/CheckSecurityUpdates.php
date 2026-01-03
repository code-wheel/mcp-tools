<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\SecurityUpdateChecker;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for checking security updates.
 *
 * @Tool(
 *   id = "mcp_tools_check_security_updates",
 *   label = @Translation("Check Security Updates"),
 *   description = @Translation("Check for available security updates for Drupal core and contributed modules."),
 *   category = "site_health",
 * )
 */
class CheckSecurityUpdates extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The security update checker service.
   */
  protected SecurityUpdateChecker $securityChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->securityChecker = $container->get('mcp_tools.security_update_checker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $securityOnly = $input['security_only'] ?? TRUE;

    return [
      'success' => TRUE,
      'data' => $this->securityChecker->getAvailableUpdates($securityOnly),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'security_only' => [
        'type' => 'boolean',
        'label' => 'Security Updates Only',
        'description' => 'If true, only return security updates. If false, return all available updates.',
        'required' => FALSE,
        'default' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_updates' => [
        'type' => 'integer',
        'label' => 'Total Updates Available',
      ],
      'security_updates' => [
        'type' => 'integer',
        'label' => 'Security Updates Count',
      ],
      'has_security_issues' => [
        'type' => 'boolean',
        'label' => 'Has Security Issues',
      ],
      'updates' => [
        'type' => 'list',
        'label' => 'Update Details',
      ],
    ];
  }

}
