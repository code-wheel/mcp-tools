<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for performing security audit.
 *
 * @Tool(
 *   id = "mcp_analysis_security",
 *   label = @Translation("Security Audit"),
 *   description = @Translation("Review permissions, check for exposed data, and identify overly permissive roles."),
 *   category = "analysis",
 * )
 */
class SecurityAudit extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return $this->analysisService->securityAudit();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required for security audit.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'critical_issues' => [
        'type' => 'array',
        'label' => 'Critical Issues',
        'description' => 'Critical security issues requiring immediate attention',
      ],
      'warnings' => [
        'type' => 'array',
        'label' => 'Warnings',
        'description' => 'Security warnings to review',
      ],
      'critical_count' => [
        'type' => 'integer',
        'label' => 'Critical Count',
      ],
      'warning_count' => [
        'type' => 'integer',
        'label' => 'Warning Count',
      ],
      'admin_user_count' => [
        'type' => 'integer',
        'label' => 'Admin User Count',
        'description' => 'Number of users with administrator role',
      ],
      'registration_mode' => [
        'type' => 'string',
        'label' => 'Registration Mode',
        'description' => 'Current user registration setting',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
