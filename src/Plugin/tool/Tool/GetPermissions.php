<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\UserAnalysisService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
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
  description: new TranslatableMarkup('Get permissions grouped by provider module: all of them, or only those matching search or one provider. A site can have hundreds, so pass search (for example "article") or provider (for example "node") when you know what you need.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'search' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search'),
      description: new TranslatableMarkup('Only permissions with this text in their name or title, any case.'),
      required: FALSE,
    ),
    'provider' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Provider'),
      description: new TranslatableMarkup('Only the permissions of this module (machine name, for example "node").'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total_permissions' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Permissions'),
      description: new TranslatableMarkup('Total number of permissions available on the site.'),
    ),
    'providers' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Provider Count'),
      description: new TranslatableMarkup('Number of modules in by_provider.'),
    ),
    'matching' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Matching'),
      description: new TranslatableMarkup('With search or provider: how many permissions matched.'),
    ),
    'by_provider' => new OutputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Permissions by Provider'),
      description: new TranslatableMarkup('Permissions grouped by provider module. Each permission has name (its machine name), a title when it says more than the name, a description when it has one, and restrict_access: true when it is only for trusted roles. Use name with mcp_structure_grant_permissions.'),
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
    $data = $this->userAnalysis->getPermissions(
      isset($input['search']) ? (string) $input['search'] : NULL,
      isset($input['provider']) ? (string) $input['provider'] : NULL,
    );
    if (isset($data['error'])) {
      return ['success' => FALSE, 'error' => $data['error']];
    }
    return ['success' => TRUE, 'data' => $data];
  }

}
