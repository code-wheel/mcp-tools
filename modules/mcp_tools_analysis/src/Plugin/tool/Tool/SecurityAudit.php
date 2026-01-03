<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\tool\Tool;

use Drupal\mcp_tools_analysis\Service\AnalysisService;
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
  id: 'mcp_analysis_security',
  label: new TranslatableMarkup('Security Audit'),
  description: new TranslatableMarkup('Review permissions, check for exposed data, and identify overly permissive roles.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'critical_issues' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Critical Issues'),
      description: new TranslatableMarkup('Critical security issues requiring immediate attention'),
    ),
    'warnings' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Warnings'),
      description: new TranslatableMarkup('Security warnings to review'),
    ),
    'critical_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Critical Count'),
      description: new TranslatableMarkup(''),
    ),
    'warning_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Warning Count'),
      description: new TranslatableMarkup(''),
    ),
    'admin_user_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Admin User Count'),
      description: new TranslatableMarkup('Number of users with administrator role'),
    ),
    'registration_mode' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Registration Mode'),
      description: new TranslatableMarkup('Current user registration setting'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class SecurityAudit extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'analysis';


  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->analysisService->securityAudit();
  }

  

  

}
