<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting Drupal site status.
 *
 * @Tool(
 *   id = "mcp_tools_get_site_status",
 *   label = @Translation("Get Site Status"),
 *   description = @Translation("Get comprehensive Drupal site status including version info, module counts, cron status, and maintenance mode."),
 *   category = "site_health",
 * )
 *
 * @todo Verify Tool API plugin structure and update annotation/attributes as needed.
 */
class GetSiteStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The site health service.
   */
  protected SiteHealthService $siteHealth;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->siteHealth = $container->get('mcp_tools.site_health');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->siteHealth->getSiteStatus(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required for this tool.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'drupal_version' => [
        'type' => 'string',
        'label' => 'Drupal Version',
      ],
      'php_version' => [
        'type' => 'string',
        'label' => 'PHP Version',
      ],
      'database' => [
        'type' => 'map',
        'label' => 'Database Information',
      ],
      'site_name' => [
        'type' => 'string',
        'label' => 'Site Name',
      ],
      'modules' => [
        'type' => 'map',
        'label' => 'Module Summary',
      ],
      'cron' => [
        'type' => 'map',
        'label' => 'Cron Status',
      ],
      'maintenance_mode' => [
        'type' => 'boolean',
        'label' => 'Maintenance Mode',
      ],
    ];
  }

}
