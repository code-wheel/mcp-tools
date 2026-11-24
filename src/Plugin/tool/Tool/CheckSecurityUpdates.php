<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\SecurityUpdateChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
    'total_updates' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Updates Available'),
      description: new TranslatableMarkup('Total number of modules with available updates.'),
    ),
    'security_updates' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Security Updates Count'),
      description: new TranslatableMarkup('Number of security-related updates (requires immediate attention).'),
    ),
    'has_security_issues' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Has Security Issues'),
      description: new TranslatableMarkup('True if any security updates are available.'),
    ),
    'complete' => new OutputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Complete'),
      description: new TranslatableMarkup('Whether every project was checked. If not, no security issue found means nothing for the projects in not_checked.'),
    ),
    'not_checked' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Not checked'),
      description: new TranslatableMarkup('Projects Drupal could not check (not fetched, pending, unknown).'),
    ),
    'last_checked' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Last checked'),
      description: new TranslatableMarkup('When Drupal last fetched update data (ISO 8601), or null.'),
    ),
    'note' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Note'),
      description: new TranslatableMarkup('Set when the answer is not complete, and what to do.'),
    ),
    'updates' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Update Details'),
      description: new TranslatableMarkup('Array of updates with name, current_version, recommended_version, security (bool), and link to release notes.'),
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

    $data = $this->securityChecker->getAvailableUpdates($securityOnly);
    // A check that could not fetch anything is not "no updates".
    if (isset($data['error'])) {
      return ['success' => FALSE, 'error' => $data['error']];
    }
    return [
      'success' => TRUE,
      'data' => $data,
    ];
  }

}
