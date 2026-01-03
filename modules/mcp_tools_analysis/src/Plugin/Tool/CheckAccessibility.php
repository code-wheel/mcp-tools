<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for checking accessibility of content.
 *
 * @Tool(
 *   id = "mcp_analysis_accessibility",
 *   label = @Translation("Check Accessibility"),
 *   description = @Translation("Perform basic accessibility checks: images without alt, heading order, link text quality."),
 *   category = "analysis",
 * )
 */
class CheckAccessibility extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $entityType = $input['entity_type'] ?? '';
    $entityId = isset($input['entity_id']) ? (int) $input['entity_id'] : 0;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'entity_type is required.'];
    }

    if ($entityId < 1) {
      return ['success' => FALSE, 'error' => 'entity_id must be a positive integer.'];
    }

    return $this->analysisService->checkAccessibility($entityType, $entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'Entity type to check (e.g., "node")',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
        'description' => 'ID of the entity to check',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
      ],
      'title' => [
        'type' => 'string',
        'label' => 'Title',
      ],
      'issues' => [
        'type' => 'array',
        'label' => 'Accessibility Issues',
        'description' => 'Issues found with type, severity, WCAG reference, and message',
      ],
      'error_count' => [
        'type' => 'integer',
        'label' => 'Error Count',
      ],
      'warning_count' => [
        'type' => 'integer',
        'label' => 'Warning Count',
      ],
      'info_count' => [
        'type' => 'integer',
        'label' => 'Info Count',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
