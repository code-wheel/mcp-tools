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
  id: 'mcp_tools_get_roles',
  label: new TranslatableMarkup('Get Roles'),
  description: new TranslatableMarkup('Get all user roles with their permissions.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total_roles' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Roles'),
      description: new TranslatableMarkup('Number of roles defined on the site.'),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Array of roles with id (machine name), label, weight, is_admin, and permissions array. Use id when assigning roles to users.'),
    ),
  ],
)]
class GetRoles extends McpToolsToolBase {

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
    return [
      'success' => TRUE,
      'data' => $this->userAnalysis->getRoles(),
    ];
  }

  

  

}
