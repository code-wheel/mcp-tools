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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_permissions',
  label: new TranslatableMarkup('Get Permissions'),
  description: new TranslatableMarkup('Get all available permissions grouped by provider module.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_permissions' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Permissions'),
      description: new TranslatableMarkup('Total number of permissions available on the site.'),
    ),
    'providers' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Provider Count'),
      description: new TranslatableMarkup('Number of modules providing permissions.'),
    ),
    'by_provider' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Permissions by Provider'),
      description: new TranslatableMarkup('Permissions grouped by provider module. Each permission has id (machine name), title, and description. Use id with GrantPermissions.'),
    ),
  ],
)]
class GetPermissions extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  /**
   * The user analysis.
   *
   * @var \Drupal\mcp_tools\Service\UserAnalysisService
   */
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
    return [
      'success' => TRUE,
      'data' => $this->userAnalysis->getPermissions(),
    ];
  }

}
