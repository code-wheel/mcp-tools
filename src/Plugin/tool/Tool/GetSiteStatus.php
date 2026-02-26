<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\SiteHealthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_site_status',
  label: new TranslatableMarkup('Get Site Status'),
  description: new TranslatableMarkup('Get comprehensive Drupal site status including version info, module counts, cron status, and maintenance mode.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'drupal_version' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Drupal Version'),
      description: new TranslatableMarkup('Drupal core version (e.g., "10.2.3").'),
    ),
    'php_version' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('PHP Version'),
      description: new TranslatableMarkup('PHP version running the site (e.g., "8.2.10").'),
    ),
    'database' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Database Information'),
      description: new TranslatableMarkup('Database driver, version, and connection details.'),
    ),
    'site_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Site Name'),
      description: new TranslatableMarkup('Configured site name from system.site config.'),
    ),
    'modules' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Module Summary'),
      description: new TranslatableMarkup('Counts of enabled/disabled modules by type (core, contrib, custom).'),
    ),
    'cron' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Cron Status'),
      description: new TranslatableMarkup('Last cron run timestamp and status.'),
    ),
    'maintenance_mode' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Maintenance Mode'),
      description: new TranslatableMarkup('True if site is in maintenance mode.'),
    ),
  ],
)]
class GetSiteStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The site health service.
   */
  protected SiteHealthService $siteHealth;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->siteHealth = $container->get('mcp_tools.site_health');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->siteHealth->getSiteStatus(),
    ];
  }

}
