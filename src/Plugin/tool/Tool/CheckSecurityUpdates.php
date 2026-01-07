<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\SecurityUpdateChecker;
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
  id: 'mcp_tools_check_security_updates',
  label: new TranslatableMarkup('Check Security Updates'),
  description: new TranslatableMarkup('Check for available security updates for Drupal core and contributed modules.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'security_only' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Security Updates Only'),
      description: new TranslatableMarkup('If true, only return security updates. If false, return all available updates.'),
      required: FALSE,
      default_value: TRUE,
    ),
  ],
  output_definitions: [
    'total_updates' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Updates Available'),
      description: new TranslatableMarkup('Total number of modules with available updates.'),
    ),
    'security_updates' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Security Updates Count'),
      description: new TranslatableMarkup('Number of security-related updates (requires immediate attention).'),
    ),
    'has_security_issues' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Security Issues'),
      description: new TranslatableMarkup('True if any security updates are available.'),
    ),
    'updates' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Update Details'),
      description: new TranslatableMarkup('Array of updates with name, installed_version, recommended_version, security (bool), and link to release notes.'),
    ),
  ],
)]
class CheckSecurityUpdates extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The security update checker service.
   */
  protected SecurityUpdateChecker $securityChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->securityChecker = $container->get('mcp_tools.security_update_checker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $securityOnly = $input['security_only'] ?? TRUE;

    return [
      'success' => TRUE,
      'data' => $this->securityChecker->getAvailableUpdates($securityOnly),
    ];
  }

  

  

}
